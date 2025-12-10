<?php

declare(strict_types=1);

namespace Payplug\Payments\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Payplug\Payments\Helper\Config;

class ApmFilteringMode implements OptionSourceInterface
{
    /**
     * Get available APM filtering modes
     *
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Config::APM_FILTERING_MODE_SHIPPING_ADDRESS,
                'label' => __('Filter by Shipping Address (Recommended)'),
            ],
            [
                'value' => Config::APM_FILTERING_MODE_BILLING_ADDRESS,
                'label' => __('Validate by Billing Address'),
            ]
        ];
    }
}
