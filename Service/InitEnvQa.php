<?php

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Payplug\Core\APIRoutes as BaseAPIRoutes;

class InitEnvQa
{
    public const XML_PATH_ENABLE_QA = 'payplug_payments/qa/enable';
    private const XML_PATH_PAYPLUG_API_URL_KEY = 'payplug_payments/qa/api_url';
    private const XML_PATH_PAYPLUG_SERVICE_URL_KEY = 'payplug_payments/qa/service_url';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function execute(): bool
    {
        $apiUrl = $this->scopeConfig->getValue(self::XML_PATH_PAYPLUG_API_URL_KEY);
        $serviceUrl = $this->scopeConfig->getValue(self::XML_PATH_PAYPLUG_SERVICE_URL_KEY);

        if ($this->isQaEnabled() === false || empty($apiUrl) || empty($serviceUrl)) {
            return false;
        }

        BaseAPIRoutes::setApiBaseUrl($apiUrl);
        BaseAPIRoutes::setServiceBaseUrl($serviceUrl);

        return true;
    }

    public function isQaEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLE_QA);
    }
}
