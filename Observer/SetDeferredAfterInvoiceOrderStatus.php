<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Payplug\Payments\Gateway\Config\Standard;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Logger\Logger;

class SetDeferredAfterInvoiceOrderStatus implements ObserverInterface
{
    /**
     * @param Config $config
     * @param Logger $logger
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        protected Config $config,
        protected Logger $logger,
        protected OrderRepositoryInterface $orderRepository
    ) {
    }

    /**
     * For standard deferred orders only. As changing the order status do not save in the invoice creation process
     *
     * As changing the order status do not save in the invoice creation process,
     * we must observe the invoice to change the status to processing later.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var OrderInterface $order */
        $order = $observer->getOrder();
        $payment = $order->getPayment();

        if ($payment?->getMethod() !== Standard::METHOD_CODE
            || !$payment?->getAdditionalInformation('is_paid')
            || !$payment?->getAdditionalInformation('was_deferred')
        ) {
            return;
        }

        $order->setStatus(Order::STATE_PROCESSING);
        $this->orderRepository->save($order);
    }
}
