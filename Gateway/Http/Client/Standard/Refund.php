<?php

namespace Payplug\Payments\Gateway\Http\Client\Standard;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\Order\Payment;

class Refund implements ClientInterface
{
    /**
     * @var Logger
     */
    private $payplugLogger;

    /**
     * @param Logger $payplugLogger
     */
    public function __construct(Logger $payplugLogger)
    {
        $this->payplugLogger = $payplugLogger;
    }

    /**
     * Place refund request
     *
     * @param TransferInterface $transferObject
     *
     * @return array
     *
     * @throws \Exception
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $data = $transferObject->getBody();

        try {
            /** @var Payment $orderPayment */
            $orderPayment = $data['payment'];
            $metadata = ['reason' => "Refunded with Magento."];
            $payment = $orderPayment->makeRefund((float)$data['amount'], $metadata, (int)$data['store_id']);

            return ['payment' => $payment, 'order_payment' => $orderPayment];
        } catch (PayplugException $e) {
            $this->payplugLogger->error($e->__toString());
            throw new \Exception(__('Error while refunding online. Please try again or contact us.'));
        } catch (\Exception $e) {
            $this->payplugLogger->error($e->getMessage());
            throw new \Exception(__('Error while refunding online. Please try again or contact us.'));
        }
    }
}
