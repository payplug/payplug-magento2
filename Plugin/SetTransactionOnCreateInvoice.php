<?php

declare(strict_types=1);

namespace Payplug\Payments\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order\Payment\Operations\ProcessInvoiceOperation;
use Payplug\Exception\ConfigurationException;
use Payplug\Exception\ConfigurationNotSetException;
use Payplug\Exception\UndefinedAttributeException;
use Payplug\Payments\Helper\Data as PayplugDataHelper;
use Payplug\Payments\Logger\Logger as PayplugLogger;
use Payplug\Payments\Model\OrderPaymentRepository as PayplugOrderPaymentRepository;

/**
 * Prevent dupicate transaction with wrong order status, in case of manual invoice creation with already
 * captured payment
 */
class SetTransactionOnCreateInvoice
{
    /**
     * @param PayplugDataHelper $payplugDataHelper
     * @param PayplugOrderPaymentRepository $orderPaymentRepository
     * @param PayplugLogger $payplugLogger
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        private readonly PayplugDataHelper $payplugDataHelper,
        private readonly PayplugOrderPaymentRepository $orderPaymentRepository,
        private readonly PayplugLogger $payplugLogger,
        private readonly CartRepositoryInterface $cartRepository,
    ) {
    }

    /**
     * Around execute method
     *
     * @param ProcessInvoiceOperation $subject
     * @param callable $proceed
     * @param OrderPaymentInterface $orderPayment
     * @param InvoiceInterface $invoice
     * @param string $operationMethod
     * @return OrderPaymentInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws ConfigurationException
     * @throws ConfigurationNotSetException
     */
    public function aroundExecute(
        ProcessInvoiceOperation $subject,
        callable $proceed,
        OrderPaymentInterface $orderPayment,
        InvoiceInterface $invoice,
        string $operationMethod
    ): OrderPaymentInterface {
        $order = $orderPayment->getOrder();
        $lastTransId = $orderPayment->getLastTransId();
        $quote = $this->cartRepository->get($order->getQuoteId());

        if (!$this->payplugDataHelper->isCodePayplugPayment($orderPayment->getMethod())
            || $this->payplugDataHelper->canCaptureOnline(null, $quote)
        ) {
            return $proceed($orderPayment, $invoice, $operationMethod);
        }

        if ($lastTransId && $invoice->getTransactionId() === null) {
            $invoice->setTransactionId($lastTransId);
        }

        try {
            $payplugPayment = $this->orderPaymentRepository->get($lastTransId, 'payment_id');
        } catch (NoSuchEntityException $e) {
            $this->payplugLogger->error($e->getMessage());
            return $proceed($orderPayment, $invoice, $operationMethod);
        }

        $payplugPaymentResource = $payplugPayment->retrieve(
            $payplugPayment->getScopeId($order),
            $payplugPayment->getScope($order)
        );

        try {
            if ($payplugPaymentResource->__get('is_paid') === true) {
                $invoice->pay();
            }
        } catch (UndefinedAttributeException $e) {
            $this->payplugLogger->error($e->getMessage());
            throw new LocalizedException(__('The invoice cannot be created'));
        }

        return $orderPayment;
    }
}
