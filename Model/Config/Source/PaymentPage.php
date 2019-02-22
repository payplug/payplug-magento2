<?php

namespace Payplug\Payments\Model\Config\Source;

use Payplug\Payments\Helper\Config;

class PaymentPage implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => Config::PAYMENT_PAGE_REDIRECT,
                'label' => __('Redirect'),
            ],
            [
                'value' => Config::PAYMENT_PAGE_EMBEDDED,
                'label' => __('Embedded'),
            ],
        ];

        return $options;
    }
}
