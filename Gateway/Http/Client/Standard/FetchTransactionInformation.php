<?php

namespace Payplug\Payments\Gateway\Http\Client\Standard;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Payplug\Payments\Model\Order\Payment;

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

        /** @var Payment $orderPayment */
        $orderPayment = $data['payment'];
        $payment = $orderPayment->retrieve((int)$data['store_id']);

        return ['payment' => $payment];
    }
}
