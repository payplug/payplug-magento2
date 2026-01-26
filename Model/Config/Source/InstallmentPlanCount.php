<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Model\Config\Source;

class InstallmentPlanCount implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Get available InstallmentPlan split options
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => 2,
                'label' => __('2 times'),
            ],
            [
                'value' => 3,
                'label' => __('3 times'),
            ],
            [
                'value' => 4,
                'label' => __('4 times'),
            ],
        ];

        return $options;
    }
}
