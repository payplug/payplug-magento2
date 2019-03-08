<?php

namespace Payplug\Payments\Gateway\Config;

class PayplugPayment extends \Magento\Payment\Gateway\Config\Config
{
    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
     * @param int|null $storeId
     *
     * @return mixed
     */
    public function getValue($field, $storeId = null)
    {
        if ($field === 'order_place_redirect_url') {
            // Prevent order email sending when placing the order
            return true;
        }

        return parent::getValue($field, $storeId);
    }
}
