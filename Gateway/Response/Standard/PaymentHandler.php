<?php

declare(strict_types=1);

namespace Payplug\Payments\Gateway\Response\Standard;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;
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

        if ($paymentDO->getPayment() instanceof Payment) {
            $payplugPayment = $response['payment'];

            /** @var Payment $payment */
            $payment = $paymentDO->getPayment();
            $isPaid = 1;

            if (!$payplugPayment->is_paid && $payplugPayment->failure === null) {
                // save payment url for pending redirect/lightbox payment
                $payment->setAdditionalInformation(
                    'payment_url',
                    $payplugPayment->hosted_payment ? $payplugPayment->hosted_payment->payment_url : ''
                );
                $isPaid = 0;
            }

            $payment->setAdditionalInformation('is_paid', $isPaid);
            $payment->setAdditionalInformation('payplug_payment_id', $payplugPayment->id);
            $payment->setAdditionalInformation('quote_id', $payment->getOrder()->getQuoteId());

            if ($this->config->isStandardPaymentModeDeferred()) {
                $payment->setAdditionalInformation('is_deferred_payment_standard', true);
            }

            if (isset($payplugPayment->payment_method['merchant_session'])) {
                $payment->setAdditionalInformation(
                    'merchand_session',
                    $payplugPayment->payment_method['merchant_session']
                );
            }

            $payment->setTransactionId($payplugPayment->id);
            $payment->setIsTransactionPending(true);
            $payment->setIsTransactionClosed(false);
            $payment->setShouldCloseParentTransaction(false);

            $orderPayment = $this->payplugPaymentFactory->create();
            $orderPayment->setOrderId($payment->getOrder()->getIncrementId());
            $orderPayment->setPaymentId($payplugPayment->id);
            $orderPayment->setIsSandbox(!$payplugPayment->is_live);

            $this->orderPaymentRepository->save($orderPayment);
        }
    }
}
