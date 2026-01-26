<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Helper;

use Magento\Config\App\Config\Type\System;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Payplug\Exception\ConfigurationException;
use Payplug\Payments\Service\GetOauth2AccessTokenData;
use Payplug\Payplug;

class Config
{
    public const CONFIG_PATH = 'payplug_payments/general/';
    public const OAUTH_CONFIG_PATH = 'payplug_payments/oauth2/';
    public const APPLE_PAY_CONFIG_PATH = 'payment/payplug_payments_apple_pay/';
    public const ONEY_CONFIG_PATH = 'payment/payplug_payments_oney/';
    public const ONEY_WITHOUT_FEES_CONFIG_PATH = 'payment/payplug_payments_oney_without_fees/';
    public const PAYPLUG_PAYMENT_ACTION_CONFIG_PATH = 'payment/payplug_payments_standard/payment_action';
    public const EMAIL_WEBSITE_OWNER_CONFIG_PATH = 'trans_email/ident_general/email';
    public const ENVIRONMENT_TEST = 'test';
    public const ENVIRONMENT_LIVE = 'live';
    public const PAYMENT_PAGE_REDIRECT = 'redirect';
    public const PAYMENT_PAGE_EMBEDDED = 'embedded';
    public const PAYMENT_PAGE_INTEGRATED = 'integrated';
    public const OAUTH_ENVIRONMENT_MODE = 'environmentmode';
    public const OAUTH_CLIENT_DATA = 'client_data';
    public const OAUTH_EMAIL = 'email';
    public const APM_FILTERING_MODE_SHIPPING_ADDRESS = 'shipping_address';
    public const APM_FILTERING_MODE_BILLING_ADDRESS = 'billing_address';
    public const MODULE_VERSION = '4.6.3';
    public const STANDARD_PAYMENT_AUTHORIZATION_ONLY = 'authorize';
    /**
     * @var string|null
     */
    private ?string $scope = null;
    /**
     * @var mixed|null
     */
    private mixed $scopeId = null;

    /**
     * @param WriterInterface $configWriter
     * @param System $systemConfigType
     * @param ProductMetadataInterface $productMetadata
     * @param StoreManagerInterface $storeManager
     * @param GetOauth2AccessTokenData $getOauth2AccessTokenData
     * @param RequestInterface $request
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly System $systemConfigType,
        private readonly ProductMetadataInterface $productMetadata,
        private readonly StoreManagerInterface $storeManager,
        private readonly GetOauth2AccessTokenData $getOauth2AccessTokenData,
        private readonly RequestInterface $request,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Initialize scope data
     *
     * @return void
     */
    public function initScopeData(): void
    {
        $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $scopeId = 0;

        $website = $this->request->getParam('website');
        $store = $this->request->getParam('store');

        if ($website) {
            $scope = ScopeInterface::SCOPE_WEBSITES;
            $scopeId = $website;
        }

        if ($store) {
            $scope = ScopeInterface::SCOPE_STORES;
            $scopeId = $store;
        }

        $this->scope = $scope;
        $this->scopeId = (int)$scopeId;
    }

    /**
     * Get the standard payment mode
     *
     * @param string $scope
     * @param int|null $websiteId
     * @return string|null
     */
    public function getStandardPaymentMode(
        string $scope = ScopeInterface::SCOPE_WEBSITES,
        ?int $websiteId = null
    ): ?string {
        return (string)$this->getConfigValue('', $scope, $websiteId, self::PAYPLUG_PAYMENT_ACTION_CONFIG_PATH);
    }

    /**
     * Check if standard payment mode is deferred
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isStandardPaymentModeDeferred(): bool
    {
        $websiteId = $this->storeManager->getStore()->getWebsiteId();
        $standardMode = $this->getStandardPaymentMode(ScopeInterface::SCOPE_WEBSITES, (int)$websiteId);

        return $standardMode === self::STANDARD_PAYMENT_AUTHORIZATION_ONLY;
    }

    /**
     * Get the email of the website owner
     *
     * @return string|null
     * @throws NoSuchEntityException
     */
    public function getWebsiteOwnerEmail(): ?string
    {
        $websiteId = $this->storeManager->getStore()->getWebsiteId();

        return (string)$this->getConfigValue(
            '',
            ScopeInterface::SCOPE_WEBSITES,
            (int)$websiteId,
            self::EMAIL_WEBSITE_OWNER_CONFIG_PATH
        );
    }

    /**
     * Get the scope of the configuration
     *
     * @return string|null
     */
    public function getConfigScope(): ?string
    {
        return $this->scope;
    }

    /**
     * Get the scope id of the configuration
     *
     * @return int|null
     */
    public function getConfigScopeId(): ?int
    {
        return $this->scopeId;
    }

    /**
     * Set the Payplug API key
     *
     * @param int|null $storeId
     * @param bool $isSandbox
     * @param string|null $scope
     * @return void
     * @throws ConfigurationException
     */
    public function setPayplugApiKey(?int $storeId, bool $isSandbox, ?string $scope = ScopeInterface::SCOPE_STORE): void
    {
        $key = $this->getApiKey($isSandbox, $storeId, $scope);

        if (!empty($key)) {
            Payplug::init(['secretKey' => $key, 'apiVersion' => '2019-08-06']);
        }
    }

    /**
     * Check if the module is connected to Payplug
     *
     * @param string|null $scope
     * @param int|null $storeId
     * @return bool
     */
    public function isLegacyConnected(?string $scope = '', ?int $storeId = null): bool
    {
        $email = $this->getConfigValue('email', $scope, $storeId);
        if ($this->scope == ScopeConfigInterface::SCOPE_TYPE_DEFAULT) {
            return (bool)$email;
        }

        $defaultEmail = $this->getConfigValue('email', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0);

        return !empty($email) && (empty($defaultEmail) || $email !== $defaultEmail);
    }

    /**
     * Check if the module is connected to Payplug using OAuth2
     *
     * @param string|null $scope
     * @param int|null $storeId
     * @return bool
     */
    public function isOauthConnected(?string $scope = '', ?int $storeId = null): bool
    {
        $email = $this->getConfigValue('email', $scope, $storeId, self::OAUTH_CONFIG_PATH);
        if ($this->scope == ScopeConfigInterface::SCOPE_TYPE_DEFAULT) {
            return (bool)$email;
        }

        $defaultEmail = $this->getConfigValue('email', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0);

        return !empty($email) && (empty($defaultEmail) || $email !== $defaultEmail);
    }

    /**
     * Get the API key to use with Payplug
     *
     * @param bool|null $isSandbox
     * @param int|null $scopeId
     * @param string|null $scope
     * @return string|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getApiKey(
        ?bool $isSandbox,
        ?int $scopeId = null,
        ?string $scope = ScopeInterface::SCOPE_STORE
    ): ?string {
        if ($scopeId === null && $this->scopeId !== null) {
            $scope = $this->scope;
            $scopeId = $this->scopeId;
        }

        if ($this->isOauthConnected($scope, $scopeId)) {
            $websiteId = $scopeId;
            if ($scope === ScopeInterface::SCOPE_STORE) {
                $websiteId = $this->storeManager->getStore($scopeId)->getWebsiteId();
            }
            if ($scope === ScopeConfigInterface::SCOPE_TYPE_DEFAULT) {
                $websiteId = 0;
            }

            try {
                $accessTokenData = $this->getOauth2AccessTokenData->execute((int)$websiteId);
                return $accessTokenData['access_token'];
            } catch (LocalizedException) {
                $accessTokenData = $this->getOauth2AccessTokenData->execute((int)$websiteId, true);
                return $accessTokenData['access_token'];
            }
        }

        return $isSandbox ? $this->getConfigValue('test_api_key', $scope, $scopeId)
            : $this->getConfigValue('live_api_key', $scope, $scopeId);
    }

    /**
     * Get is sandbox mode is ON
     *
     * @param int|null $store
     * @return bool
     */
    public function getIsSandbox(?int $store = null): bool
    {
        $environmentMode = (string)$this->getConfigValue('environmentmode', ScopeInterface::SCOPE_STORE, $store);

        return $environmentMode === self::ENVIRONMENT_TEST;
    }

    /**
     * Get if the integraded payment method is enabled
     *
     * @return bool
     */
    public function isEmbedded(): bool
    {
        return (string)$this->getConfigValue('payment_page') === self::PAYMENT_PAGE_EMBEDDED;
    }

    /**
     * Get if shipping apm filtering mode is enabled
     *
     * @return bool
     */
    public function isShippingApmFilteringMode(): bool
    {
        return (string)$this->getConfigValue('apm_filtering_mode') === self::APM_FILTERING_MODE_SHIPPING_ADDRESS;
    }

    /**
     * Get if the payment page is integrated mode
     *
     * @return bool
     */
    public function isIntegrated(): bool
    {
        return (string)$this->getConfigValue('payment_page') === self::PAYMENT_PAGE_INTEGRATED;
    }

    /**
     * Get if OneClick mode is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isOneClick(?int $storeId = null): bool
    {
        return $this->getConfigValue('can_save_cards', ScopeInterface::SCOPE_STORE, $storeId) &&
            $this->getConfigValue(
                'one_click',
                ScopeInterface::SCOPE_STORE,
                $storeId,
                'payment/payplug_payments_standard/'
            );
    }

    /**
     * Get module version
     *
     * @return string
     */
    public function getModuleVersion(): string
    {
        return self::MODULE_VERSION;
    }

    /**
     * Get magento version
     *
     * @return string
     */
    public function getMagentoVersion(): string
    {
        return $this->productMetadata->getVersion();
    }

    /**
     * Get config value
     *
     * @param string $field
     * @param string $scope
     * @param int|null $scopeId
     * @param string|null $path
     * @return mixed
     */
    public function getConfigValue(
        string $field,
        string $scope = ScopeInterface::SCOPE_STORE,
        ?int $scopeId = null,
        ?string $path = self::CONFIG_PATH
    ): mixed {
        if ($scopeId === null && $this->scopeId !== null) {
            $scope = $this->scope;
            $scopeId = $this->scopeId;
        }

        return $this->scopeConfig->getValue(
            $path . $field,
            $scope,
            $scopeId
        );
    }

    /**
     * Set config value
     *
     * @param string $field
     * @param string $value
     * @param string $scope
     * @param int|null $scopeId
     * @param string|null $path
     * @return void
     */
    public function setConfigValue(
        string $field,
        string $value,
        string $scope = ScopeInterface::SCOPE_STORE,
        ?int $scopeId = null,
        ?string $path = null
    ): void {
        if ($scopeId === null && $this->scopeId !== null) {
            $scope = $this->scope;
            $scopeId = $this->scopeId;
        }

        if ($path === null) {
            $path = self::CONFIG_PATH;
        }

        $this->configWriter->save($path . $field, $value, $scope, $scopeId);
    }

    /**
     * Clear config
     *
     * @return void
     */
    public function clearConfig(): void
    {
        $keys = [
            // General configuration
            'payplug_payments/general/test_api_key',
            'payplug_payments/general/live_api_key',
            'payplug_payments/general/connected',
            'payplug_payments/general/currencies',
            'payplug_payments/general/min_amounts',
            'payplug_payments/general/max_amounts',
            'payplug_payments/general/company_id',
            'payplug_payments/general/verified',
            'payplug_payments/general/email',
            'payplug_payments/general/pwd',
            'payplug_payments/general/environmentmode',
            'payplug_payments/general/payment_page',
            'payplug_payments/general/invoice_on_payment',
            'payplug_payments/general/oney_countries',
            'payplug_payments/general/oney_min_amounts',
            'payplug_payments/general/oney_max_amounts',
            'payplug_payments/general/use_live_mode',
            'payplug_payments/general/can_save_cards',
            'payplug_payments/general/can_create_installment_plan',
            'payplug_payments/general/can_create_deferred_payment',
            'payplug_payments/general/can_use_oney',
            'payplug_payments/general/merchand_country',
            'payplug_payments/general/payment_page_backup',
            'payplug_payments/general/can_use_integrated_payments',
            // OAUTH 2 configuration
            'payplug_payments/oauth2/email',
            'payplug_payments/oauth2/client_data',
            'payplug_payments/oauth2/access_token_data',
            // Payplug payment Standard configuration
            'payment/payplug_payments_standard/active',
            'payment/payplug_payments_standard/title',
            'payment/payplug_payments_standard/one_click',
            'payment/payplug_payments_standard/invoice_on_payment',
            'payment/payplug_payments_standard/processing_order_status',
            'payment/payplug_payments_standard/canceled_order_status',
            'payment/payplug_payments_standard/allowspecific',
            'payment/payplug_payments_standard/specificcountry',
            'payment/payplug_payments_standard/default_country',
            'payment/payplug_payments_standard/sort_order',
            // Payplug payment Installment plan configuration
            'payment/payplug_payments_installment_plan/active',
            'payment/payplug_payments_installment_plan/title',
            'payment/payplug_payments_installment_plan/count',
            'payment/payplug_payments_installment_plan/threshold',
            'payment/payplug_payments_installment_plan/processing_order_status',
            'payment/payplug_payments_installment_plan/canceled_order_status',
            'payment/payplug_payments_installment_plan/allowspecific',
            'payment/payplug_payments_installment_plan/specificcountry',
            'payment/payplug_payments_installment_plan/default_country',
            'payment/payplug_payments_installment_plan/sort_order',
            // Payplug payment On demand configuration
            'payment/payplug_payments_ondemand/active',
            'payment/payplug_payments_ondemand/title',
            'payment/payplug_payments_ondemand/processing_order_status',
            'payment/payplug_payments_ondemand/canceled_order_status',
            'payment/payplug_payments_ondemand/allowspecific',
            'payment/payplug_payments_ondemand/specificcountry',
            'payment/payplug_payments_ondemand/default_country',
            'payment/payplug_payments_ondemand/sort_order',
            // Payplug payment Oney configuration
            'payment/payplug_payments_oney/active',
            'payment/payplug_payments_oney/title',
            'payment/payplug_payments_oney/processing_order_status',
            'payment/payplug_payments_oney/canceled_order_status',
            'payment/payplug_payments_oney/oney_min_amounts',
            'payment/payplug_payments_oney/oney_max_amounts',
            'payment/payplug_payments_oney/oney_min_threshold',
            'payment/payplug_payments_oney/oney_max_threshold',
            'payment/payplug_payments_oney/allowspecific',
            'payment/payplug_payments_oney/specificcountry',
            'payment/payplug_payments_oney/sort_order',
            // Payplug payment Oney without feexs configuration
            'payment/payplug_payments_oney_without_fees/active',
            'payment/payplug_payments_oney_without_fees/title',
            'payment/payplug_payments_oney_without_fees/processing_order_status',
            'payment/payplug_payments_oney_without_fees/canceled_order_status',
            'payment/payplug_payments_oney_without_fees/oney_min_threshold',
            'payment/payplug_payments_oney_without_fees/oney_max_threshold',
            'payment/payplug_payments_oney_without_fees/allowspecific',
            'payment/payplug_payments_oney_without_fees/specificcountry',
            'payment/payplug_payments_oney_without_fees/sort_order',
            // Payplug payment Bancontact configuration
            'payment/payplug_payments_bancontact/active',
            'payment/payplug_payments_bancontact/title',
            'payment/payplug_payments_bancontact/processing_order_status',
            'payment/payplug_payments_bancontact/canceled_order_status',
            'payment/payplug_payments_bancontact/allowspecific',
            'payment/payplug_payments_bancontact/specificcountry',
            'payment/payplug_payments_bancontact/default_country',
            'payment/payplug_payments_bancontact/sort_order',
            // Payplug payment Apple Pay configuration
            'payment/payplug_payments_apple_pay/active',
            'payment/payplug_payments_apple_pay/title',
            'payment/payplug_payments_apple_pay/show_on_cart',
            'payment/payplug_payments_apple_pay/show_on_checkout',
            'payment/payplug_payments_apple_pay/processing_order_status',
            'payment/payplug_payments_apple_pay/canceled_order_status',
            'payment/payplug_payments_apple_pay/allowspecific',
            'payment/payplug_payments_apple_pay/specificcountry',
            'payment/payplug_payments_apple_pay/default_country',
            'payment/payplug_payments_apple_pay/sort_order',
        ];

        $pproMethods = [
            'satispay',
            'ideal',
            'mybank',
        ];

        $paths = [
            'payment/payplug_payments_%s/active',
            'payment/payplug_payments_%s/title',
            'payment/payplug_payments_%s/processing_order_status',
            'payment/payplug_payments_%s/canceled_order_status',
            'payment/payplug_payments_%s/allowspecific',
            'payment/payplug_payments_%s/default_country',
            'payment/payplug_payments_%s/sort_order',
            'payplug_payments/general/%s_countries',
            'payplug_payments/general/%s_min_amounts',
            'payplug_payments/general/%s_max_amounts',
            'payplug_payments/general/can_use_%s',
        ];

        foreach ($pproMethods as $method) {
            foreach ($paths as $path) {
                $keys[] = sprintf($path, $method);
            }
        }

        foreach ($keys as $key) {
            $this->configWriter->delete($key, $this->scope, $this->scopeId);
        }
        $this->systemConfigType->clean();
    }

    /**
     * Clear legacy auth config
     *
     * @return void
     */
    public function clearLegacyAuthConfig(): void
    {
        $keys = [
            'payplug_payments/general/test_api_key',
            'payplug_payments/general/live_api_key',
            'payplug_payments/general/connected',
            'payplug_payments/general/verified',
            'payplug_payments/general/email',
            'payplug_payments/general/pwd'
        ];

        foreach ($keys as $key) {
            $this->configWriter->delete($key, $this->scope, $this->scopeId);
        }

        $this->systemConfigType->clean();
    }

    /**
     * Get amounts allowed by currency
     *
     * @param string|null $isoCode
     * @param int|null $storeId
     * @param string|null $path
     * @param string|null $amountPrefix
     * @return array|bool
     */
    public function getAmountsByCurrency(
        ?string $isoCode,
        ?int $storeId,
        ?string $path,
        ?string $amountPrefix = ''
    ): array|bool {
        $minAmounts = [];
        $maxAmounts = [];

        if ($path === null) {
            $path = self::CONFIG_PATH;
        }

        $minAmountsConfig = $this->getConfigValue(
            $amountPrefix . 'min_amounts',
            ScopeInterface::SCOPE_STORE,
            $storeId,
            $path
        );
        $maxAmountsConfig = $this->getConfigValue(
            $amountPrefix . 'max_amounts',
            ScopeInterface::SCOPE_STORE,
            $storeId,
            $path
        );

        if (empty($minAmountsConfig) || empty($maxAmountsConfig) || !$isoCode) {
            return false;
        }

        foreach (explode(';', $minAmountsConfig) as $amountCur) {
            $cur = [];
            if (preg_match('/^([A-Z]{3}):([0-9]*)$/', $amountCur, $cur)) {
                $minAmounts[$cur[1]] = (int)$cur[2];
            } else {
                return false;
            }
        }
        foreach (explode(';', $maxAmountsConfig) as $amountCur) {
            $cur = [];
            if (preg_match('/^([A-Z]{3}):([0-9]*)$/', $amountCur, $cur)) {
                $maxAmounts[$cur[1]] = (int)$cur[2];
            } else {
                return false;
            }
        }

        if (!isset($minAmounts[$isoCode]) || !isset($maxAmounts[$isoCode])) {
            return false;
        } else {
            $currentMinAmount = $minAmounts[$isoCode];
            $currentMaxAmount = $maxAmounts[$isoCode];
        }

        return ['min_amount' => $currentMinAmount, 'max_amount' => $currentMaxAmount];
    }

    /**
     * Get Apple Pay disallowed shipping methods
     *
     * @return array
     */
    public function getApplePayDisallowedShippingMethods(): array
    {
        return array_map(
            'trim',
            explode(
                ',',
                (string)$this->getConfigValue(
                    'excluded_shipping_method',
                    ScopeInterface::SCOPE_STORE,
                    null,
                    self::APPLE_PAY_CONFIG_PATH
                )
            )
        );
    }
}
