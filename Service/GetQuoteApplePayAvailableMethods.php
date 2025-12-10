<?php

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Quote\Api\ShippingMethodManagementInterface;
use Payplug\Payments\Helper\Config;

class GetQuoteApplePayAvailableMethods
{
    /**
     * @param ShippingMethodManagementInterface $shippingMethodManagement
     * @param Config $payPlugConfig
     */
    public function __construct(
        private readonly ShippingMethodManagementInterface $shippingMethodManagement,
        private readonly Config $payPlugConfig
    ) {
    }

    /**
     * Get a formated array with method available for Apple pay on quote
     *
     * @param int $quoteId
     * @return array
     * @throws NoSuchEntityException
     * @throws StateException
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
