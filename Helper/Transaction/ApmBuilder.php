<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Helper\Transaction;

use Magento\Payment\Model\InfoInterface;

class ApmBuilder extends AbstractBuilder
{
    /**
     * @inheritdoc
     */
    public function buildCustomerData($order, InfoInterface $payment, $quote): array
    {
        $customerData = parent::buildCustomerData($order, $payment, $quote);

        if ($this->payplugConfig->isShippingApmFilteringMode() === false) {
            return $customerData;
        }

        // When Shipping APM Filtering Mode ON, override billing address with shipping address to bypass API validation.
        $filteredShippingData = array_filter($customerData['shipping']);
        unset($filteredShippingData['delivery_type']);

        $customerData['billing'] = array_merge($customerData['billing'], $filteredShippingData);

        return $customerData;
    }
}
