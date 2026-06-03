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
use Payplug\Payments\Helper\Data as PayplugDataHelper;

class MarkOrderPaymentAsHeadless implements ObserverInterface
{
    public const PAYPLUG_IS_HEADLESS = 'payplug_is_headless';

    /**
     * @param PayplugDataHelper $payplugDataHelper
     */
    public function __construct(
        private readonly PayplugDataHelper $payplugDataHelper
    ) {
    }

    /**
     * Persist headless context on the order payment so downstream public endpoints can recognize it.
     *
     * Registered only in the graphql area: any quote submission there is a headless place-order.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var OrderInterface $order */
        $order = $observer->getData('order');
        $payment = $order->getPayment();

        if ($payment === null || $this->payplugDataHelper->isCodePayplugPayment($payment->getMethod()) === false) {
            return;
        }

        $payment->setAdditionalInformation(self::PAYPLUG_IS_HEADLESS, true);
    }
}
