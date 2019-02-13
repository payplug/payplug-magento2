<?php

namespace Payplug\Payments\Controller\Payment;

use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Payplug\Exception\PayplugException;

class LightboxUrl extends AbstractPayment
{
    public function execute()
    {
        /** @var Json $response */
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $responseParams = [
            'url' => $this->_url->getUrl('payplug_payments/payment/cancel', ['is_canceled_by_provider' => true]),
            'error' => true,
            'message' => __('An error occurred while processing the order.')
        ];

        try {
            $lastIncrementId = $this->getCheckout()->getLastRealOrderId();

            if (!$lastIncrementId) {
                $this->logger->error('Could not retrieve last order id');
                $response->setData($responseParams);
                return $response;
            }
            $order = $this->salesOrderFactory->create();
            $order->loadByIncrementId($lastIncrementId);

            if (!$order->getId()) {
                $this->logger->error(sprintf('Could not retrieve order with id %s', $lastIncrementId));
                $response->setData($responseParams);
                return $response;
            }

            $paymentMethod = $order->getPayment()->getMethodInstance();
            $payment = $paymentMethod->createPayplugTransaction($order);
            $paymentMethod->setOrderPendingPayment($order);
            $url = $payment->hosted_payment->payment_url;

            $response->setData([
                'url' => $url,
                'error' => false,
            ]);

            return $response;
        } catch (PayplugException $e) {
            $this->logger->error($e->__toString());
            $response->setData($responseParams);
            return $response;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $response->setData($responseParams);
            return $response;
        }
    }
}
