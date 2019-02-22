<?php

namespace Payplug\Payments\Model\Config\Source;

use Payplug\Payments\Helper\Config;

class Mode implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => Config::ENVIRONMENT_TEST,
                'label' => __('Test'),
            ],
            [
                'value' => Config::ENVIRONMENT_LIVE,
                'label' => __('Live'),
            ],
        ];

        return $options;
    }
}
