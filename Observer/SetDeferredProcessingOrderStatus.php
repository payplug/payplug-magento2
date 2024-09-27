<?php

declare(strict_types=1);

namespace Payplug\Payments\Observer;

use Payplug\Payments\Gateway\Config\Standard;
use Payplug\Payments\Helper\Config;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Payplug\Payments\Logger\Logger;

class SetDeferredProcessingOrderStatus implements ObserverInterface
{
    public function __construct(
        protected Config $config,
        protected Logger $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Order $order */
        $order = $observer->getOrder();

        if (!$this->config->isStandardPaymentModeDeferred()
            || $order->getPayment()?->getMethod() !== Standard::METHOD_CODE
            || $order->hasInvoices()
        ) {
            return;
        }

        $statusHistories = $order->getStatusHistories();
        $lastStatusHistory = array_pop($statusHistories);
        $status = $this->config->getStandardAuthorizedStatus();

        $order->setStatus($status);
        $lastStatusHistory->setStatus($status);
    }
}
