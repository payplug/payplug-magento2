<?php

declare(strict_types=1);

namespace Payplug\Payments\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class AuthorizationType implements ArrayInterface
{
    /**
     * Options getter
     */
    public function toOptionArray(): array
    {
        return [['value' => 1, 'label' => __('Authorization and Capture')], ['value' => 0, 'label' => __('Authorization only')]];
    }

    /**
     * Get options in "key-value" format
     */
    public function toArray(): array
    {
        return [0 => __('Authorization only'), 1 => __('Authorization and Capture')];
    }
}
