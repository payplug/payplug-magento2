<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Model\Method\Adapter as MethodAdapter;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Gateway\Config\Scalapay as ScalapayConfig;
use Payplug\Payments\Helper\Config as ConfigHelper;

class DisableScalapayOutsideSubtotalRange implements ObserverInterface
{
    /**
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        private readonly ConfigHelper $configHelper
    ) {
    }

    /**
     * Hide payment method if subtotal is outside allowed range
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var CartInterface $quote */
        $quote = $observer->getEvent()->getData('quote');

        if ($quote === null) {
            return;
        }

        /** @var MethodAdapter $methodAdapter */
        $methodAdapter = $observer->getEvent()->getData('method_instance');
        $paymentMethod = $methodAdapter->getCode();

        if ($paymentMethod !== ScalapayConfig::METHOD_CODE) {
            return;
        }

        $minAmountConfigValue = $this->configHelper->getConfigValue('scalapay_min_amounts');
        $maxAmountConfigValue = $this->configHelper->getConfigValue('scalapay_max_amounts');

        list(, $minAmount) = explode(':', $minAmountConfigValue);
        list(, $maxAmount) = explode(':', $maxAmountConfigValue);

        $minAmount = (float) ($minAmount / 100);
        $maxAmount = (float) ($maxAmount / 100);

        $minThresholdAmount = (float) $this->configHelper->getConfigValue(
            'min_threshold',
            ScopeInterface::SCOPE_STORE,
            $quote->getStoreId(),
            ConfigHelper::SCALAPAY_CONFIG_PATH
        );
        $maxThresholdAmount = (float) $this->configHelper->getConfigValue(
            'max_threshold',
            ScopeInterface::SCOPE_STORE,
            $quote->getStoreId(),
            ConfigHelper::SCALAPAY_CONFIG_PATH
        );

        $finalMinAmount = max($minAmount, $minThresholdAmount);
        $finalMaxAmount = min($maxAmount, $maxThresholdAmount);

        $quoteGrandTotal = $quote->getGrandTotal();

        if ($quoteGrandTotal >= $finalMinAmount && $quoteGrandTotal <= $finalMaxAmount) {
            return;
        }

        /** @var DataObject $checkResult */
        $checkResult = $observer->getEvent()->getData('result');
        $checkResult->setData('is_available', false);
    }
}
