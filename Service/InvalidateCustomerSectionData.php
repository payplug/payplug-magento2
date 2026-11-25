<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Session\Config as SessionConfig;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Logger\Logger as PayplugLogger;
use Throwable;

class InvalidateCustomerSectionData
{
    private const CUSTOMER_DATA_SESSION_COOKIE = 'mage-cache-sessid';

    /**
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param PayplugLogger $payplugLogger
     */
    public function __construct(
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly PayplugLogger $payplugLogger
    ) {
    }

    /**
     * Delete the customer data session cookie so the storefront reloads the private content sections
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            $this->deleteCookie();
        } catch (Throwable $e) {
            $this->payplugLogger->error('Could not invalidate the customer section data', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete the customer data session cookie as host-only and, when configured, on the cookie domain
     *
     * @return void
     * @throws FailureToSendException
     * @throws InputException
     */
    private function deleteCookie(): void
    {
        $this->cookieManager->deleteCookie(
            self::CUSTOMER_DATA_SESSION_COOKIE,
            $this->cookieMetadataFactory->createCookieMetadata()->setPath('/')
        );

        $cookieDomain = (string)$this->scopeConfig->getValue(
            SessionConfig::XML_PATH_COOKIE_DOMAIN,
            ScopeInterface::SCOPE_STORE
        );

        if ($cookieDomain === '') {
            return;
        }

        $this->cookieManager->deleteCookie(
            self::CUSTOMER_DATA_SESSION_COOKIE,
            $this->cookieMetadataFactory->createCookieMetadata()->setPath('/')->setDomain($cookieDomain)
        );
    }
}
