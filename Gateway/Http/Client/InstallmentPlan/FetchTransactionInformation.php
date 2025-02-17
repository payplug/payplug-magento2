<?php

namespace Payplug\Payments\Gateway\Http\Client\InstallmentPlan;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Payplug\Payments\Model\Order\InstallmentPlan;

class FetchTransactionInformation implements ClientInterface
{
    /**
     * Place Retrieve request
     *
     * @param TransferInterface $transferObject
     *
     * @return array
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $data = $transferObject->getBody();

        /** @var InstallmentPlan $orderInstallmentPlan */
        $orderInstallmentPlan = $data['installment_plan'];
        $installmentPlan = $orderInstallmentPlan->retrieve((int)$data['store_id']);

        return ['installment_plan' => $installmentPlan, 'order_installment_plan' => $orderInstallmentPlan];
    }
}
