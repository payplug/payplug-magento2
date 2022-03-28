<?php

namespace Payplug\Payments\Gateway\Request\InstallmentPlan;

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
     * @param Data          $payplugHelper
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

        $payplugInstallmentPlan = $this->payplugHelper->getOrderInstallmentPlan($order->getOrderIncrementId());

        return [
            'installment_plan' => $payplugInstallmentPlan,
            'store_id' => $order->getStoreId(),
        ];
    }
}
