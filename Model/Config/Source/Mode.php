<?php

namespace Payplug\Payments\Model\Config\Source;

use Payplug\Payments\Model\PaymentMethod;

class Mode implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => PaymentMethod::ENVIRONMENT_TEST,
                'label' => __('Test'),
            ],
            [
                'value' => PaymentMethod::ENVIRONMENT_LIVE,
                'label' => __('Live'),
            ],
        ];

        return $options;
    }
}
