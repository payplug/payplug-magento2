<?php

namespace Payplug\Payments\Controller\Payment;

use Magento\Framework\Exception\PaymentException;
use Payplug\Exception\PayplugException;

class OneClick extends AbstractPayment
{
    public function execute()
    {
        try {
            $lastIncrementId = $this->getCheckout()->getLastRealOrderId();

            if (!$lastIncrementId) {
                throw new \Exception('Could not retrieve last order id');
            }
            $order = $this->salesOrderFactory->create();
            $order->loadByIncrementId($lastIncrementId);

            if (!$order->getId()) {
                throw new \Exception(sprintf('Could not retrieve order with id %s', $lastIncrementId));
            }

            $customerCardId = $this->getRequest()->getParam('customer_card_id');
            if (empty($customerCardId)) {
                throw new PaymentException(__('Could not retrieve customer card id'));
            }

            $paymentMethod = $order->getPayment()->getMethodInstance();
            $payment = $paymentMethod->createPayplugTransaction($order, $customerCardId);

            if (!$payment->is_paid) {
                $failureMessage = $this->payplugHelper->getPaymentErrorMessage($payment);
                $this->_forward('cancel', null, null, ['failure_message' => $failureMessage]);
                return;
            }

            $paymentMethod->setOrderPendingPayment($order);
            $this->_forward('paymentReturn', null, null, ['_secure' => true, 'quote_id' => $order->getQuoteId()]);
        } catch (PayplugException $e) {
            $this->logger->error($e->__toString());
            $this->messageManager->addErrorMessage(
                __('An error occured while processing your payment. Please try again.')
            );
            $this->_forward('cancel', null, null, ['is_canceled_by_provider' => true]);
        } catch (PaymentException $e) {
            $this->logger->error($e->getMessage());
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->_forward('cancel', null, null, ['is_canceled_by_provider' => true]);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $this->messageManager->addErrorMessage(
                __('An error occured while processing your payment. Please try again.')
            );
            $this->_forward('cancel', null, null, ['is_canceled_by_provider' => true]);
        }
    }
}
