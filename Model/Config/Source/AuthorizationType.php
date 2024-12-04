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
        return [['value' => 'authorize_capture', 'label' => __('Authorization and Capture')], ['value' => 'authorize', 'label' => __('Authorization only')]];
    }

    /**
     * Get options in "key-value" format
     */
    public function toArray(): array
    {
        return ['authorize' => __('Authorization only'), 'authorize_capture' => __('Authorization and Capture')];
    }
}
