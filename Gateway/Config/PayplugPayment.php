<?php

declare(strict_types=1);

namespace Payplug\Payments\Gateway\Config;

use Magento\Payment\Gateway\Config\Config;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\MethodInterface;

class PayplugPayment extends Config
{
    /**
     * Get the value of a configuration field.
     *
     * @param string $field
     * @param int|string $storeId
     * @return mixed|string|true|null
     */
    public function getValue($field, $storeId = null)
    {
        if ($field === 'order_place_redirect_url') {
            // Prevent order email sending when placing the order
            return true;
        }

        if ($field === 'payment_action'
            && parent::getValue($field, $storeId) === MethodInterface::ACTION_AUTHORIZE_CAPTURE
            && $this->getValue('invoice_on_payment', $storeId)
        ) {
            return AbstractMethod::ACTION_ORDER;
        }

        return parent::getValue($field, $storeId);
    }
}
