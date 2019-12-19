<?php

namespace Payplug\Payments\Gateway\Request\Payment;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Payplug\Payments\Gateway\Helper\SubjectReader;
use Payplug\Payments\Helper\Transaction\AbstractBuilder;

class TransactionDataBuilder implements BuilderInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var AbstractBuilder
     */
    private $builder;
    
    /**
     * @param SubjectReader   $subjectReader
     * @param AbstractBuilder $builder
     */
    public function __construct(
        SubjectReader $subjectReader,
        AbstractBuilder $builder
    ) {
        $this->subjectReader = $subjectReader;
        $this->builder = $builder;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject)
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);

        $order = $paymentDO->getOrder();
        $payment = $paymentDO->getPayment();
        $quote = $this->subjectReader->getQuote();

        return $this->builder->buildTransaction($order, $payment, $quote);
    }
}
