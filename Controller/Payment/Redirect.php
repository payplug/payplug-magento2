<?php

namespace Payplug\Payments\Controller\Payment;

use Payplug\Exception\PayplugException;

class Redirect extends AbstractPayment
{
    public function execute()
    {
        try {
            $lastIncrementId = $this->getCheckout()->getLastRealOrderId();

            if (!$lastIncrementId) {
                $this->logger->error('Could not retrieve last order id');
                $this->_forward('cancel', null, null, ['is_canceled_by_provider' => true]);
                return;
            }
            $order = $this->salesOrderFactory->create();
            $order->loadByIncrementId($lastIncrementId);

            if (!$order->getId()) {
                $this->logger->error(sprintf('Could not retrieve order with id %s', $lastIncrementId));
                $this->_forward('cancel', null, null, ['is_canceled_by_provider' => true]);
                return;
            }

            $paymentMethod = $order->getPayment()->getMethodInstance();

            $payment = $paymentMethod->createPayplugTransaction($order);
            $paymentMethod->setOrderPendingPayment($order);
            $url = $payment->hosted_payment->payment_url;

            return $this->resultRedirectFactory->create()->setUrl($url);
        } catch (PayplugException $e) {
            $this->logger->error($e->__toString());
            $this->_forward('cancel', null, null, ['is_canceled_by_provider' => true]);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $this->_forward('cancel', null, null, ['is_canceled_by_provider' => true]);
        }
    }
}
