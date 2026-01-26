<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Model\Config\Source;

class AllowedCountries implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Get available payment modes
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 0, 'label' => __('All Allowed Countries')],
        ];
    }
}
