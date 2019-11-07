<?php

namespace Payplug\Payments\Helper\Http;

class InstallmentPlanClient extends AbstractClient
{
    /**
     * @inheritdoc
     */
    protected function prepareReturnData($payplugObject, $data)
    {
        return ['installment_plan' => $payplugObject];
    }

    /**
     * @inheritdoc
     */
    protected function createPayplugObject($payplugData)
    {
        return \Payplug\InstallmentPlan::create($payplugData);
    }
}
