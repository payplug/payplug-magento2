<?php

namespace Payplug\Payments\Model\Config\Source;

use Payplug\Payments\Helper\Config;

class Mode implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Get available payment modes
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => Config::ENVIRONMENT_TEST,
                'label' => __('PayPlug TEST mode'),
            ],
            [
                'value' => Config::ENVIRONMENT_LIVE,
                'label' => __('PayPlug LIVE mode'),
            ],
        ];

        return $options;
    }
}
