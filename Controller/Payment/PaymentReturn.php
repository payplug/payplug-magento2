<?php

namespace Payplug\Payments\Controller\Payment;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;

class PaymentReturn extends AbstractPayment
{
    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session\Proxy $checkoutSession
     * @param \Magento\Sales\Model\OrderFactory     $salesOrderFactory
     * @param Logger                                $logger
     * @param Data                                  $payplugHelper
     * @param OrderRepository                       $orderRepository
     */
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

            $order = $this->payplugHelper->updateOrder($order);

            if ($order->getState() == Order::STATE_PROCESSING) {
                return $this->_redirect($redirectUrlSuccess);
            } else {
                $this->_forward('cancel', null, null, ['is_canceled_by_provider' => true]);
            }
        } catch (PayplugException $e) {
            $this->logger->error($e->__toString());
            $this->_forward('cancel', null, null, ['is_canceled_by_provider' => true]);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $this->_forward('cancel', null, null, ['is_canceled_by_provider' => true]);
        }
    }
}
