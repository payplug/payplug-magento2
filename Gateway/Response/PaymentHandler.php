<?php

namespace Payplug\Payments\Gateway\Response;

use Payplug\Payments\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;
use Payplug\Payments\Model\OrderPaymentRepository;

class PaymentHandler implements HandlerInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var \Payplug\Payments\Model\Order\PaymentFactory
     */
    private $payplugPaymentFactory;

    /**
     * @var OrderPaymentRepository
     */
    private $orderPaymentRepository;

    /**
     * TransactionIdHandler constructor.
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        SubjectReader $subjectReader,
        \Payplug\Payments\Model\Order\PaymentFactory $payplugPaymentFactory,
        OrderPaymentRepository $orderPaymentRepository
    ) {
        $this->subjectReader = $subjectReader;
        $this->payplugPaymentFactory = $payplugPaymentFactory;
        $this->orderPaymentRepository = $orderPaymentRepository;
    }

    /**
     * Handles response
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = $this->subjectReader->readPayment($handlingSubject);

        if ($paymentDO->getPayment() instanceof Payment) {
            $payplugPayment = $response['payment'];

            /** @var Payment $payment */
            $payment = $paymentDO->getPayment();

            if ($payplugPayment->is_paid) {
                // Successfull one click payment
                $this->setUpdatePayment($payment, $payplugPayment, false);
            } else {
                // Failed one click payment or pending redirect/lightbox payment
                $this->setUpdatePayment($payment, $payplugPayment);
            }

            /** @var \Payplug\Payments\Model\Order\Payment $orderPayment */
            $orderPayment = $this->payplugPaymentFactory->create();
            $orderPayment->setOrderId($payment->getOrder()->getIncrementId());
            $orderPayment->setPaymentId($payplugPayment->id);
            $orderPayment->setIsSandbox(!$payplugPayment->is_live);
            $this->orderPaymentRepository->save($orderPayment);
        }
    }

    /**
     * @param Payment                   $payment
     * @param \Payplug\Resource\Payment $payplugPayment
     * @param bool                      $pending
     */
    private function setUpdatePayment($payment, $payplugPayment, $pending = true)
    {
        $payment->setTransactionId($payplugPayment->id);
        if ($pending) {
            $payment->setAdditionalInformation('payment_url', $payplugPayment->hosted_payment->payment_url);
        }
        $payment->setIsTransactionPending($pending);
        $payment->setIsTransactionClosed(!$pending);
        $payment->setShouldCloseParentTransaction(!$pending);
    }
}
