<?php

namespace Payplug\Payments\Controller\Payment;

use Payplug\Payments\Model\PaymentMethod;

class PaymentReturn extends AbstractPayment
{
    public function execute()
    {
        $redirectUrlSuccess = 'checkout/onepage/success';
        try {
            $lastIncrementId = $this->getCheckout()->getLastRealOrderId();

            if (!$lastIncrementId) {
                $this->logger->error('Could not retrieve last order id');
                return $this->_redirect($redirectUrlSuccess);
            }
            $order = $this->salesOrderFactory->create();
            $order->loadByIncrementId($lastIncrementId);

            if (!$order->getId()) {
                $this->logger->error(sprintf('Could not retrieve order with id %s', $lastIncrementId));
                return $this->_redirect($redirectUrlSuccess);
            }

            $orderPayment = $this->payplugHelper->getOrderPayment($order->getId());

            if (!$orderPayment->getId()) {
                $this->logger->error(sprintf("Could not retrieve order payment for order %s", $order->getIncrementId()));
                return $this->_redirect($redirectUrlSuccess);
            }

            $environmentMode = PaymentMethod::ENVIRONMENT_LIVE;
            if ($orderPayment->isSandbox()) {
                $environmentMode = PaymentMethod::ENVIRONMENT_TEST;
            }

            $paymentId = $orderPayment->getPaymentId();
            $payment = $orderPayment->retrieve($paymentId, $environmentMode, $order->getStoreId());

            if ($payment->failure) {
                $failureMessage = $this->payplugHelper->getPaymentErrorMessage($payment);
                $this->_forward('cancel', null, null, ['failure_message' => $failureMessage]);
            } else {
                $this->paymentMethod->processOrder($order, $paymentId);
                return $this->_redirect($redirectUrlSuccess);
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $this->_forward('cancel', null, null, ['is_canceled_by_provider' => true]);
        }
    }
}