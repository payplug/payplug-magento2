<?php

namespace Payplug\Payments\Gateway\Http\Client\Standard;

use Exception;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\Order\Payment;

class Refund implements ClientInterface
{
    /**
     * @param Logger $payplugLogger
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly MessageManagerInterface $messageManager,
        private readonly Logger $payplugLogger
    ) {
    }

    /**
     * @throws Exception
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $data = $transferObject->getBody();

        try {
            /** @var Payment $orderPayment */
            $orderPayment = $data['payment'];
            $metadata = ['reason' => "Refunded with Magento."];
            $payment = $orderPayment->makeRefund((float)$data['amount'], $metadata, (int)$data['store_id']);

            if ($payment->object === 'error'
                && $this->request instanceof HttpRequest
                && $this->request->getFullActionName() === 'sales_order_creditmemo_save'
            ) {
                $this->messageManager->addNoticeMessage(__($payment->message));
            }

            return ['payment' => $payment, 'order_payment' => $orderPayment];
        } catch (PayplugException $e) {
            $this->payplugLogger->error($e->__toString());
            throw new Exception(__('Error while refunding online. Please try again or contact us.'));
        } catch (Exception $e) {
            $this->payplugLogger->error($e->getMessage());
            throw new Exception(__('Error while refunding online. Please try again or contact us.'));
        }
    }
}
