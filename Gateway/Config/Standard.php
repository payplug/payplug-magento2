<?php

namespace Payplug\Payments\Gateway\Config;

class Standard extends \Magento\Payment\Gateway\Config\Config
{
    const METHOD_CODE = 'payplug_payments_standard';

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
