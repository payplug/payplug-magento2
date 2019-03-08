<?php

namespace Payplug\Payments\Gateway\Http\Client\Standard;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Model\Order\Payment;

class Refund implements ClientInterface
{
    public function placeRequest(TransferInterface $transferObject)
    {
        $data = $transferObject->getBody();

        /** @var Payment $orderPayment */
        $orderPayment = $data['payment'];
        $metadata = ['reason' => "Refunded with Magento."];
        $payment = $orderPayment->makeRefund($data['amount'], $metadata, $data['store_id']);

        return ['payment' => $payment, 'order_payment' => $orderPayment];
    }
}
