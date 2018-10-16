<?php

namespace Payplug\Payments\Model\Config\Source;

use Payplug\Payments\Model\PaymentMethod;

class PaymentPage implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => PaymentMethod::PAYMENT_PAGE_REDIRECT,
                'label' => __('Redirect'),
            ],
            [
                'value' => PaymentMethod::PAYMENT_PAGE_EMBEDDED,
                'label' => __('Embedded'),
            ],
        ];

        return $options;
    }
}
