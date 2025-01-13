<?php

declare(strict_types=1);

namespace Payplug\Payments\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Config\Config;

class PayplugPayment extends Config
{
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        $methodCode = null,
        $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
    }

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
