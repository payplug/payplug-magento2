<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\ViewModel;

use Magento\Framework\Pricing\Helper\Data as PricingDataHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class Oney implements ArgumentInterface
{
    /**
     * @param PricingDataHelper $priceHelper
     */
    public function __construct(
        private readonly PricingDataHelper $priceHelper
    ) {
    }

    /**
     * Format price with currency
     *
     * @param float $value
     * @return float|string
     */
    public function currency(float $value): float|string
    {
        return $this->priceHelper->currency($value, true, false);
    }
}
