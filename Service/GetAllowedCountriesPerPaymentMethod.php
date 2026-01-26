<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Framework\Serialize\SerializerInterface;
use Payplug\Payments\Helper\Config as PayplugConfigHelper;

class GetAllowedCountriesPerPaymentMethod
{
    /**
     * @param PayplugConfigHelper $payplugConfigHelper
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly PayplugConfigHelper $payplugConfigHelper,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Get allowed countries per payment method
     *
     * @param string $paymentMethod
     * @return array
     */
    public function execute(string $paymentMethod): array
    {
        $paymentMethod = str_replace('payplug_payments_', '', $paymentMethod);

        $restrictedCountryIds = $this->serializer->unserialize(
            $this->payplugConfigHelper->getConfigValue($paymentMethod . '_countries') ?? '[]'
        );

        $restrictedCountryOverrideIds = $this->serializer->unserialize(
            $this->payplugConfigHelper->getConfigValue($paymentMethod . '_countries_override') ?? '[]'
        );

        if ($restrictedCountryOverrideIds) {
            $restrictedCountryIds = $restrictedCountryOverrideIds;
        }

        return $restrictedCountryIds ?: [];
    }
}
