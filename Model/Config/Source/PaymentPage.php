<?php

namespace Payplug\Payments\Model\Config\Source;

use Payplug\Payments\Model\Payment\AbstractPaymentMethod;

class PaymentPage implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => AbstractPaymentMethod::PAYMENT_PAGE_REDIRECT,
                'label' => __('Redirect'),
            ],
            [
                'value' => AbstractPaymentMethod::PAYMENT_PAGE_EMBEDDED,
                'label' => __('Embedded'),
            ],
        ];

        return $options;
    }
}
