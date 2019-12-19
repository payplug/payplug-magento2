<?php

namespace Payplug\Payments\Gateway\Response\Ondemand;

use Payplug\Payments\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;
use Payplug\Payments\Helper\Ondemand;

class PaymentHandler implements HandlerInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var Ondemand
     */
    private $ondemandHelper;

    /**
     * @param SubjectReader $subjectReader
     * @param Ondemand      $ondemandHelper
     */
    public function __construct(SubjectReader $subjectReader, Ondemand $ondemandHelper)
    {
        $this->subjectReader = $subjectReader;
        $this->ondemandHelper = $ondemandHelper;
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
            $this->ondemandHelper->saveTransactionInfo($payment, $response);
        }
    }
}
