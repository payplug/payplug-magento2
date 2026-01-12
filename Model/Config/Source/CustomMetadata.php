<?php

declare(strict_types=1);

namespace Payplug\Payments\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Sales\Api\Data\OrderInterface;

class CustomMetadata implements OptionSourceInterface
{
    private const METADATA_SHIPPING_METHOD_KEY = 'shipping_method';

    /**
     * Get available additional metadata
     *
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => '',
                'label' => __('-- Please Select --'),
            ],
            [
                'value' => OrderInterface::ENTITY_ID,
                'label' => __('Order ID'),
            ],
            [
                'value' => OrderInterface::CUSTOMER_ID,
                'label' => __('Customer ID'),
            ],
            [
                'value' => OrderInterface::CUSTOMER_GROUP_ID,
                'label' => __('Customer Group'),
            ],
            [
                'value' => OrderInterface::STORE_ID,
                'label' => __('Store ID'),
            ],
            [
                'value' => OrderInterface::COUPON_CODE,
                'label' => __('Coupon Code'),
            ],
            [
                'value' => OrderInterface::TOTAL_ITEM_COUNT,
                'label' => __('Number of Items'),
            ],
            [
                'value' => self::METADATA_SHIPPING_METHOD_KEY,
                'label' => __('Shipping Method'),
            ]
        ];
    }
}
