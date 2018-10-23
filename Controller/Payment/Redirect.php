<?php

namespace Payplug\Payments\Controller\Payment;

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

            $payment = $this->paymentMethod->createPayplugTransaction($order);
            $this->paymentMethod->setOrderPendingPayment($order);
            $url = $payment->hosted_payment->payment_url;

            return $this->resultRedirectFactory->create()->setUrl($url);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $this->_forward('cancel', null, null, ['is_canceled_by_provider' => true]);
        }
    }
}
