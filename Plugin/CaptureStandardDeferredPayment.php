<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Plugin;

use Exception;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Operations\ProcessInvoiceOperation;
use Payplug\Payments\Gateway\Config\Standard;
use Payplug\Payments\Helper\Config as PayplugConfig;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\Order\Payment;
use Payplug\Payments\Model\OrderPaymentRepository;
use Payplug\Payments\Service\BuildHostedFieldsParamsHash;

class CaptureStandardDeferredPayment
{
    /**
     * @param Data $data
     * @param Logger $logger
     * @param ManagerInterface $eventManager
     * @param OrderPaymentRepository $orderPaymentRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param PayplugConfig $payplugConfig
     * @param BuildHostedFieldsParamsHash $buildHostedFieldsParamsHash
     */
    public function __construct(
        private readonly Data $data,
        private readonly Logger $logger,
        private readonly ManagerInterface $eventManager,
        private readonly OrderPaymentRepository $orderPaymentRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PayplugConfig $payplugConfig,
        private readonly BuildHostedFieldsParamsHash $buildHostedFieldsParamsHash,
    ) {
    }

    /**
     * Arround execute method
     *
     * @param ProcessInvoiceOperation $subject
     * @param callable $proceed
     * @param mixed $args
     * @return OrderPaymentInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function aroundExecute(ProcessInvoiceOperation $subject, callable $proceed, ...$args): OrderPaymentInterface
    {
        if (count($args) < 2) {
            return $proceed(...$args);
        }

        /** @var OrderPaymentInterface $orderPayment */
        $orderPayment = $args[0];

        if ($orderPayment->getMethod() !== Standard::METHOD_CODE) {
            return $proceed(...$args);
        }

        /** @var InvoiceInterface $invoice */
        $invoice = $args[1];
        $orderId = $orderPayment->getOrder()->getId();
        $order = $orderId ? $this->orderRepository->get($orderId) : $orderPayment->getOrder();

        if ($this->data->canCaptureOnline($order) === false) {
            return $proceed(...$args);
        }

        $this->eventManager->dispatch(
            'sales_order_payment_capture',
            ['payment' => $orderPayment, 'invoice' => $invoice]
        );

        if ($invoice->getIsPaid()) {
            throw new LocalizedException(
                __('The transaction "%1" cannot be captured yet.', $invoice->getTransactionId())
            );
        }

        $payplugPaymentId = $orderPayment->getAdditionalInformation()['payplug_payment_id'] ?? null;

        if (!$payplugPaymentId) {
            return $orderPayment;
        }

        try {
            $payplugPayment = $this->orderPaymentRepository->get($payplugPaymentId, 'payment_id');
            $payplugPaymentResource = $payplugPayment->retrieve(
                $payplugPayment->getScopeId($order),
                $payplugPayment->getScope($order)
            );

            if ($payplugPayment->isHostedFieldsPayment() === true) {
                $payload = [
                    'method' => 'capture',
                    'params' => [
                        'IDENTIFIER' => $this->payplugConfig->getHostedFieldsIdentifier(),
                        'OPERATIONTYPE' => 'capture',
                        'TRANSACTIONID' => $payplugPaymentId,
                        'ORDERID' => $order->getIncrementId(),
                        'DESCRIPTION' => 'Order #' . $order->getIncrementId(),
                        'VERSION' => Payment::HOSTED_FIELDS_PARAMS_VERSION,
                    ],
                ];

                $payload['params']['HASH'] = $this->buildHostedFieldsParamsHash->execute(
                    $payload['params'],
                    BuildHostedFieldsParamsHash::SEPARATOR_ACCOUNT_KEY
                );

                $result = $payplugPaymentResource->capture(null, $payload);
                $execCode = $result['EXECCODE'] ?? null;

                if ($execCode !== '0000') {
                    $this->logger->error(sprintf('Could not capture hosted fields payment: %s', json_encode($result)));
                    throw new Exception('Could not capture hosted fields payment');
                }

                $updatedPayplugPaymentResource = $payplugPayment->retrieve(
                    $payplugPayment->getScopeId($order),
                    $payplugPayment->getScope($order)
                );
            } else {
                $updatedPayplugPaymentResource = $payplugPaymentResource->capture();
            }

            if ($updatedPayplugPaymentResource) {
                $orderPayment->setBaseAmountPaidOnline(
                    (float)$orderPayment->getAdditionalInformation('authorized_amount') / 100
                );
                $orderPayment->setLastTransId($payplugPaymentId);
                $orderPayment->setAdditionalInformation('is_paid', true);
                $orderPayment->setAdditionalInformation('was_deferred', true);
                $invoice->setIsPaid(true);
                $invoice->setTransactionId($payplugPaymentId);

                $order->addCommentToStatusHistory(sprintf(
                    'Payment of %s %s successfully captured and paid on Payplug at %s.',
                    (int)($updatedPayplugPaymentResource->amount) / 100,
                    $updatedPayplugPaymentResource->currency,
                    date('Y-m-d H:i:s', $updatedPayplugPaymentResource->paid_at),
                ), Order::STATE_PROCESSING);

                $this->orderRepository->save($order);
            }
        } catch (Exception $e) {
            $invoice->setIsPaid(false);
            $this->logger->info($e->getMessage());
            // If the connection fail when trying to capture the order, then we do not want the invoice to be created.
            throw new Exception($e->getMessage());
        }

        return $orderPayment;
    }
}
