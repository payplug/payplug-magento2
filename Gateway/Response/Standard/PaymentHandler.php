<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Gateway\Response\Standard;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;
use Payplug\Payments\Api\Data\OrderPaymentInterface;
use Payplug\Payments\Gateway\Helper\SubjectReader;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Model\Order\PaymentFactory;
use Payplug\Payments\Model\OrderPaymentRepository;

class PaymentHandler implements HandlerInterface
{
    /**
     * @param SubjectReader $subjectReader
     * @param PaymentFactory $payplugPaymentFactory
     * @param OrderPaymentRepository $orderPaymentRepository
     * @param Config $config
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
        private readonly PaymentFactory $payplugPaymentFactory,
        private readonly OrderPaymentRepository $orderPaymentRepository,
        private readonly Config $config
    ) {
    }

    /**
     * Handle response
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = $this->subjectReader->readPayment($handlingSubject);
        $payplugPaymentResource = $response['payment'] ?? null;

        if ($paymentDO->getPayment() instanceof Payment === false || $payplugPaymentResource === null) {
            return;
        }

        /** @var Payment $payment */
        $payment = $paymentDO->getPayment();
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);

        $isPaid = 1;

        if (!$payplugPaymentResource->is_paid && $payplugPaymentResource->failure === null) {
            // save payment url for pending redirect/lightbox payment
            $payment->setAdditionalInformation(
                'payment_url',
                $payplugPaymentResource->hosted_payment ? $payplugPaymentResource->hosted_payment->payment_url : ''
            );

            $payment->setAdditionalInformation(
                'payment_url_post_params',
                $payplugPaymentResource->hosted_payment->payment_url_post_params ?? ''
            );

            $isPaid = 0;
        }

        $payment->setAdditionalInformation('is_paid', $isPaid);
        $payment->setAdditionalInformation('payplug_payment_id', $payplugPaymentResource->id);
        $payment->setAdditionalInformation('quote_id', $order->getQuoteId());

        if ($this->config->isStandardPaymentModeDeferred()) {
            $payment->setAdditionalInformation('is_deferred_payment_standard', true);
        }

        if (isset($payplugPaymentResource->payment_method['merchant_session'])) {
            $payment->setAdditionalInformation(
                'merchand_session',
                $payplugPaymentResource->payment_method['merchant_session']
            );
        }

        $payment->setTransactionId($payplugPaymentResource->id);
        $payment->setIsTransactionPending(true);
        $payment->setIsTransactionClosed(false);
        $payment->setShouldCloseParentTransaction(false);

        $isHostedFieldsPayment = (bool) $payment->getAdditionalInformation(OrderPaymentInterface::HF_PAYMENT_KEY);

        $orderPayment = $this->payplugPaymentFactory->create();
        $orderPayment->setOrderId($order->getIncrementId());
        $orderPayment->setPaymentId($payplugPaymentResource->id);
        $orderPayment->setIsHostedFieldsPayment($isHostedFieldsPayment);

        $isLive = $isHostedFieldsPayment || ($payplugPaymentResource->is_live ?? false);

        $orderPayment->setIsSandbox($isLive === false);

        $this->orderPaymentRepository->save($orderPayment);
    }
}
