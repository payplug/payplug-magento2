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
use Magento\Sales\Api\Data\OrderInterface;
use Payplug\Payments\Gateway\Config\Standard;
use Payplug\Payments\Helper\Config as PayplugConfig;
use Throwable;

class UpdateHostedFieldPaymentBeforeQuoteSubmit implements ObserverInterface
{
    /**
     * @param PayplugConfig $payplugConfig
     */
    public function __construct(
        private readonly PayplugConfig $payplugConfig
    ) {
    }

    /**
     * Force payment transaction pending status before quote submit
     *
     * Payment will be created after quote submit
     *
     * @param Observer $observer
     * @return void
     * @throws Throwable
     */
    public function execute(Observer $observer): void
    {
        /** @var OrderInterface $order */
        $order = $observer->getEvent()->getData('order');
        $payment = $order->getPayment();
        $websiteId = (int) $order->getStore()->getWebsiteId();

        if ($payment === false
            || $payment->getMethod() !== Standard::METHOD_CODE
            || $this->payplugConfig->isHostedFieldsActive($websiteId) === false
        ) {
            return;
        }

        $payment->setIsTransactionPending(true);
        $payment->setIsTransactionClosed(false);
        $payment->setShouldCloseParentTransaction(false);
    }
}
