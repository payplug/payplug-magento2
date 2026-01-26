<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Model\Config\Source;

use Payplug\Payments\Helper\Config;

class PaymentPage implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Get available payment behavior
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => Config::PAYMENT_PAGE_INTEGRATED,
                'label' => __('PayPlug embedded payment'),
            ],
            [
                'value' => Config::PAYMENT_PAGE_REDIRECT,
                'label' => __('PayPlug redirected payment'),
            ],
            [
                'value' => Config::PAYMENT_PAGE_EMBEDDED,
                'label' => __('PayPlug pop-up payment'),
            ],
        ];

        return $options;
    }
}
