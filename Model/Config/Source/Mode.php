<?php

namespace Payplug\Payments\Model\Config\Source;

use Payplug\Payments\Model\Payment\AbstractPaymentMethod;

class Mode implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => AbstractPaymentMethod::ENVIRONMENT_TEST,
                'label' => __('Test'),
            ],
            [
                'value' => AbstractPaymentMethod::ENVIRONMENT_LIVE,
                'label' => __('Live'),
            ],
        ];

        return $options;
    }
}
