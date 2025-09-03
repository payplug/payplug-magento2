<?php

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Quote\Api\ShippingMethodManagementInterface;
use Payplug\Payments\Helper\Config;

class GetQuoteApplePayAvailableMethods
{
    public function __construct(
        private ShippingMethodManagementInterface $shippingMethodManagement,
        private Config $payPlugConfig
    ) {}

    /**
     * Get a formated array with method available for Apple pay on quote
     */
    public function execute(int $quoteId): array
    {
        $availableMethods = [];
        $methods = $this->shippingMethodManagement->getList($quoteId);
        $disalowedMethods = $this->payPlugConfig->getApplePayDisallowedShippingMethods();

        foreach ($methods as $method) {
            $methodCode = $method->getCarrierCode() . '_' . $method->getMethodCode();
            if (in_array($methodCode, $disalowedMethods)) {
                continue;
            }
            $availableMethods[] = [
                'label' => $method->getMethodTitle(),
                'identifier' => $methodCode,
                'amount' => (string)$method->getAmount(),
                'detail' => $method->getCarrierTitle()
            ];
        }
        return $availableMethods;
    }
}
