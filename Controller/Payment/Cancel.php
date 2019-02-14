<?php

namespace Payplug\Payments\Controller\Payment;

class Cancel extends AbstractPayment
{
    public function execute()
    {
        $redirectUrlCart = 'checkout/cart';
        try {
            $lastIncrementId = $this->getCheckout()->getLastRealOrderId();

            if (!$lastIncrementId) {
                $this->logger->error('Could not retrieve last order id');

                return $this->_redirect($redirectUrlCart);
            }
            $order = $this->salesOrderFactory->create();
            $order->loadByIncrementId($lastIncrementId);

            if (!$order->getId()) {
                $this->logger->error(sprintf('Could not retrieve order with id %s', $lastIncrementId));

                return $this->_redirect($redirectUrlCart);
            }

            $isCanceledByProvider = $this->_request->getParam('is_canceled_by_provider', false);
            $failureMessage = $this->_request->getParam('failure_message', null);
            $order->getPayment()->getMethodInstance()->cancelOrder($order, $isCanceledByProvider, $failureMessage);
            if ($failureMessage !== null) {
                $this->messageManager->addErrorMessage(__($failureMessage));
            }

            /* @var $cart \Magento\Checkout\Model\Cart */
            $cart = $this->_objectManager->get(\Magento\Checkout\Model\Cart::class);
            $items = $order->getItemsCollection();
            try {
                foreach ($items as $item) {
                    $cart->addOrderItem($item);
                }
            } catch (\Exception $e) {
                $cart->truncate();
                $this->messageManager->addErrorMessage(
                    __('At least one item is not available anymore. Please try again.')
                );
            }

            $cart->save();

            return $this->_redirect($redirectUrlCart);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return $this->_redirect($redirectUrlCart);
        }
    }
}
