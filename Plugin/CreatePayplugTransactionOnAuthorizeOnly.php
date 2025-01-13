<?php

declare(strict_types=1);

namespace Payplug\Payments\Plugin;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Payplug\Exception\UndefinedAttributeException;
use Payplug\Payments\Gateway\Config\Standard;
use Payplug\Payments\Helper\Config;
use Magento\Sales\Model\Order\Payment\Operations\AuthorizeOperation;
use Payplug\Payments\Helper\Http\StandardClient;
use Payplug\Payments\Helper\Transaction\StandardBuilder;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\Order\Payment;
use Payplug\Payments\Model\Order\PaymentFactory;
use Payplug\Payments\Model\OrderPaymentRepository;

class CreatePayplugTransactionOnAuthorizeOnly
{
    public function __construct(
        protected Config $config,
        protected Logger $logger,
        protected StandardBuilder $builder,
        protected CartRepositoryInterface $quoteRepository,
        protected PaymentFactory $payplugPaymentFactory,
        protected OrderPaymentRepository $payplugOrderPaymentRepository,
        protected StandardClient $standardClient
    ) {
    }

    public function afterAuthorize(AuthorizeOperation $subject, OrderPaymentInterface $payment): OrderPaymentInterface
    {
        if ($payment->getMethod() !== Standard::METHOD_CODE
        || !$this->config->isStandardPaymentModeDeferred()) {
            return $payment;
        }

        $order = $payment->getOrder();
        $newTransaction = $this->builder->buildTransaction($order, $payment, $this->quoteRepository->get($order->getQuoteId()));
        $result = $this->standardClient->placeRequest($newTransaction);
        $this->saveTransactionInfo($payment, $result);

        return $payment;
    }

    public function saveTransactionInfo(OrderPaymentInterface $payment, array $transactionData): OrderPaymentInterface
    {
        $payplugPayment = $transactionData['payment'];

        $isPaid = 1;
        if (!$payplugPayment->is_paid && $payplugPayment->failure === null) {
            // Save payment url for pending redirect/lightbox payment
            $payment->setAdditionalInformation(
                'payment_url',
                $payplugPayment->hosted_payment ? $payplugPayment->hosted_payment->payment_url : ''
            );
            $isPaid = 0;
        }
        $payment->setAdditionalInformation('is_paid', $isPaid);
        $payment->setAdditionalInformation('payplug_payment_id', $payplugPayment->id);
        $payment->setAdditionalInformation('is_deferred_payment_standard', true);
        $payment->setAdditionalInformation('quote_id', $payment->getOrder()->getQuoteId());

        try {
            if ($payplugPayment->payment_method && isset($payplugPayment->payment_method['merchant_session'])) {
                $payment->setAdditionalInformation(
                    'merchand_session',
                    $payplugPayment->payment_method['merchant_session']
                );
            }
        } catch (UndefinedAttributeException $e) {
            // "payment_method" attribute is not defined on all payment methods
        }

        $payment->setTransactionId($payplugPayment->id);
        $payment->setIsTransactionPending(true);
        $payment->setIsTransactionClosed(false);
        $payment->setShouldCloseParentTransaction(false);

        /** @var Payment $orderPayment */
        $orderPayment = $this->payplugPaymentFactory->create();
        $orderPayment->setOrderId($payment->getOrder()->getIncrementId());
        $orderPayment->setPaymentId($payplugPayment->id);
        $orderPayment->setIsSandbox(!$payplugPayment->is_live);
        $this->payplugOrderPaymentRepository->save($orderPayment);

        return $payment;
    }
}
