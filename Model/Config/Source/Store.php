<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Store implements OptionSourceInterface
{
    /**
     * Get option array
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'order_store',
                'label' => __('Order Store (Automatic)')
            ],
            [
                'value' => 'global_store',
                'label' => __('Global Store (Admin/Default)')
            ]
        ];
    }
}
