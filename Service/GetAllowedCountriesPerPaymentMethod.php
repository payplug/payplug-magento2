<?php

namespace Payplug\Payments\Service;

use Magento\Framework\Serialize\SerializerInterface;
use Payplug\Payments\Helper\Config as PayplugConfigHelper;

class GetAllowedCountriesPerPaymentMethod
{
    public function __construct(
        private readonly PayplugConfigHelper $payplugConfigHelper,
        private readonly SerializerInterface $serializer
    ) {
    }

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
