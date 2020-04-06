<?php

namespace Payplug\Payments\Gateway\Response\Oney;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment;
use Payplug\Payments\Logger\Logger;

class FetchTransactionInformationHandler implements HandlerInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var Logger
     */
    private $payplugLogger;

    /**
     * @var OrderSender
     */
    private $orderSender;

    /**
     * @param SubjectReader $subjectReader
     * @param Logger        $payplugLogger
     * @param OrderSender   $orderSender
     */
    public function __construct(
        SubjectReader $subjectReader,
        Logger $payplugLogger,
        OrderSender $orderSender
    ) {
        $this->subjectReader = $subjectReader;
        $this->payplugLogger = $payplugLogger;
        $this->orderSender = $orderSender;
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
            /** @var Payment $payment */
            $payment = $paymentDO->getPayment();
            $order = $payment->getOrder();

            $payplugPayment = $response['payment'];

            if ($payplugPayment->failure) {
                $payment->setIsTransactionDenied(true);
            } elseif ($payplugPayment->is_paid || $payplugPayment->authorization->authorized_at) {
                $payment->setIsTransactionApproved(true);
                try {
                    $this->orderSender->send($order);
                } catch (\Exception $e) {
                    $this->payplugLogger->critical($e->getMessage());
                }
            }
        }
    }
}
