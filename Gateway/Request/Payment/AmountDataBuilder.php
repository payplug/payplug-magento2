<?php

namespace Payplug\Payments\Gateway\Request\Payment;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Payplug\Payments\Gateway\Helper\SubjectReader;

class AmountDataBuilder implements BuilderInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @param SubjectReader $subjectReader
     */
    public function __construct(SubjectReader $subjectReader)
    {
        $this->subjectReader = $subjectReader;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject)
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);

        $order = $paymentDO->getOrder();

        $paymentTab = [
            'amount' => (int) ($order->getGrandTotalAmount() * 100),
        ];

        return $paymentTab;
    }
}
