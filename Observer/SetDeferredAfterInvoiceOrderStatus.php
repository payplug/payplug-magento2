<?php

declare(strict_types=1);

namespace Payplug\Payments\Observer;

use Magento\Sales\Model\OrderRepository;
use Payplug\Payments\Gateway\Config\Standard;
use Payplug\Payments\Helper\Config;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Payplug\Payments\Logger\Logger;

class SetDeferredAfterInvoiceOrderStatus implements ObserverInterface
{
    public function __construct(
        protected Config $config,
        protected Logger $logger,
        protected OrderRepository $orderRepository
    ) {
    }

    /**
     * For standard deferred orders only
     *
     * As changing the order status do not save in the invoice creation process
     * we must observe the invoice to change the status to processing later
     */
    public function execute(Observer $observer): void
    {
        /** @var Order $order */
        $order = $observer->getOrder();

        if ($order->getPayment()?->getMethod() !== Standard::METHOD_CODE
            || !$order->getPayment()?->getAdditionalInformation('is_paid')
            || !$order->getPayment()?->getAdditionalInformation('was_deferred')
        ) {
            return;
        }

        $order->setStatus(Order::STATE_PROCESSING);
        $this->orderRepository->save($order);
    }
}
