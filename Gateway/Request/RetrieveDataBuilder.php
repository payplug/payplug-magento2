<?php

namespace Payplug\Payments\Gateway\Request;

use Payplug\Payments\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Payplug\Payments\Helper\Data;

class RetrieveDataBuilder implements BuilderInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var Data
     */
    private $payplugHelper;

    /**
     * Constructor
     *
     * @param SubjectReader $subjectReader
     */
    public function __construct(SubjectReader $subjectReader, Data $payplugHelper)
    {
        $this->subjectReader = $subjectReader;
        $this->payplugHelper = $payplugHelper;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject)
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);

        $order = $paymentDO->getOrder();

        $payplugPayment = $this->payplugHelper->getOrderPayment($order->getOrderIncrementId());

        return [
            'payment' => $payplugPayment,
            'store_id' => $order->getStoreId(),
        ];
    }
}
