<?php

namespace Payplug\Payments\Controller\ApplePay;

use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\PaymentException;
use Magento\Sales\Model\Order;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Controller\Payment\AbstractPayment;

class UpdateTransaction extends AbstractPayment
{
    /**
     * Update PayPlug Apple Pay transaction data
     *
     * @return Json
     */
    public function execute()
    {
        /** @var Json $response */
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $response->setData([
            'error' => true,
            'message' => __('An error occurred while processing the order.'),
        ]);

        try {
            $order = $this->getLastOrder();
            $token = $this->getRequest()->getParam('token');
            if (empty($token)) {
                throw new \Exception('Could not retrieve token');
            }

            $payplugPayment = $this->payplugHelper->getOrderPayment($order->getIncrementId());
            $updatedPayment = $payplugPayment->update([
                'apple_pay' => [
                    'payment_token' => $token,
                ],
            ]);

            if ($updatedPayment->is_paid) {
                $response->setData([
                    'error' => false,
                ]);
            }

            return $response;
        } catch (PayplugException $e) {
            $this->logger->error('Could not update apple pay transaction', [
                'message' => $e->__toString(),
                'exception' => $e,
            ]);

            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Could not update apple pay transaction', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return $response;
        }
    }

    /**
     * Get last order
     *
     * @return Order
     *
     * @throws \Exception
     */
    private function getLastOrder()
    {
        $lastIncrementId = $this->getCheckout()->getLastRealOrderId();

        if (!$lastIncrementId) {
            throw new \Exception('Could not retrieve last order id');
        }
        $order = $this->salesOrderFactory->create();
        $order->loadByIncrementId($lastIncrementId);

        if (!$order->getId()) {
            throw new \Exception(sprintf('Could not retrieve order with id %s', $lastIncrementId));
        }

        return $order;
    }
}
