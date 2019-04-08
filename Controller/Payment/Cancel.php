<?php

namespace Payplug\Payments\Controller\Payment;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;

class Cancel extends AbstractPayment
{
    /**
     * @var OrderRepository
     */
    private $orderRepository;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session\Proxy $checkoutSession,
        \Magento\Sales\Model\OrderFactory $salesOrderFactory,
        Logger $logger,
        Data $payplugHelper,
        OrderRepository $orderRepository
    ) {
        parent::__construct($context, $checkoutSession, $salesOrderFactory, $logger, $payplugHelper);
        $this->orderRepository = $orderRepository;
    }

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
            /** @var $order Order */
            $order->loadByIncrementId($lastIncrementId);

            if (!$order->getId()) {
                $this->logger->error(sprintf('Could not retrieve order with id %s', $lastIncrementId));

                return $this->_redirect($redirectUrlCart);
            }

            $this->payplugHelper->cancelOrderAndInvoice($order);

            $failureMessage = $this->_request->getParam('failure_message', null);
            if ($failureMessage !== null) {
                $this->messageManager->addErrorMessage(__($failureMessage));
            }

            $this->rebuildCart($order);

            return $this->_redirect($redirectUrlCart);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return $this->_redirect($redirectUrlCart);
        }
    }

    /**
     * @param Order $order
     */
    private function rebuildCart($order)
    {
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
    }
}
