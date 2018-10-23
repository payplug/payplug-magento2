<?php

namespace Payplug\Payments\Controller\Payment;

class Redirect extends AbstractPayment
{
    public function execute()
    {
        try {
            $lastIncrementId = $this->_getCheckout()->getLastRealOrderId();

            if (!$lastIncrementId) {
                $this->logger->error('Could not retrieve last order id');
                return $this->_redirect(''); // TODO forward cancel
            }
            $order = $this->salesOrderFactory->create();
            $order->loadByIncrementId($lastIncrementId);

            if (!$order->getId()) {
                $this->logger->error(sprintf('Could not retrieve order with id %s', $lastIncrementId));
                return $this->_redirect('');// TODO forward cancel
            }

            $payment = $this->paymentMethod->createPayplugTransaction($order);
            $this->paymentMethod->setOrderPendingPayment($order);
            $url = $payment->hosted_payment->payment_url;

            return $this->resultRedirectFactory->create()->setUrl($url);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return $this->_redirect('');// TODO forward cancel / add error message for customer
        }
    }
}
