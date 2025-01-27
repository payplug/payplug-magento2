<?php

declare(strict_types=1);

namespace Payplug\Payments\Helper;

use Magento\Config\App\Config\Type\System;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Payplug\Payments\Model\Api\Login;
use Payplug\Payplug;

class Config extends AbstractHelper
{
    /**
     * The constant to access the configurations
     */
    public const CONFIG_PATH = 'payplug_payments/general/';
    public const ONEY_CONFIG_PATH = 'payment/payplug_payments_oney/';
    public const ONEY_WITHOUT_FEES_CONFIG_PATH = 'payment/payplug_payments_oney_without_fees/';
    public const PAYPLUG_PAYMENT_ACTION_CONFIG_PATH = 'payment/payplug_payments_standard/payment_action';
    public const PAYPLUG_PAYMENT_AUTHORIZED_STATUS_CONFIG_PATH = 'payment/payplug_payments_standard/authorized_order_status';
    public const EMAIL_WEBSITE_OWNER_CONFIG_PATH = 'trans_email/ident_general/email';
    public const ENVIRONMENT_TEST = 'test';
    public const ENVIRONMENT_LIVE = 'live';
    public const PAYMENT_PAGE_REDIRECT = 'redirect';
    public const PAYMENT_PAGE_EMBEDDED = 'embedded';
    public const PAYMENT_PAGE_INTEGRATED = 'integrated';

    public const MODULE_VERSION = '4.2.0';
    public const STANDARD_PAYMENT_AUTHORIZATION_ONLY = 'authorize';

    private ?AdapterInterface $adapter = null;
    private ?string $scope = null;
    private mixed $scopeId = null;

    public function __construct(
        Context $context,
        protected WriterInterface $configWriter,
        protected System $systemConfigType,
        protected ProductMetadataInterface $productMetadata,
        protected ResourceConnection $resourceConnection,
        protected Login $login,
        protected StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    /**
     * Init BO config scope
     */
    public function initScopeData(): void
    {
        $scope    = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $scopeId = 0;

        $website = $this->_request->getParam('website');
        $store = $this->_request->getParam('store');

        if ($website) {
            $scope    = ScopeInterface::SCOPE_WEBSITES;
            $scopeId = $website;
        }

        if ($store) {
            $scope    = ScopeInterface::SCOPE_STORES;
            $scopeId = $store;
        }

        $this->scope = $scope;
        $this->scopeId = (int)$scopeId;
    }

    /**
     * Get payment mode (authorization / authorization_capture)
     */
    public function getStandardPaymentMode(string $scope = ScopeInterface::SCOPE_WEBSITES, int $websiteId = null): ?string
    {
        return (string)$this->getConfigValue('', $scope, $websiteId, self::PAYPLUG_PAYMENT_ACTION_CONFIG_PATH);
    }

    /**
     * Return true if the standard payment is on Authorization only
     */
    public function isStandardPaymentModeDeferred(): bool
    {
        $websiteId = $this->storeManager->getStore()->getWebsiteId();

        return $this->getStandardPaymentMode(ScopeInterface::SCOPE_WEBSITES, (int)$websiteId) === self::STANDARD_PAYMENT_AUTHORIZATION_ONLY;
    }

    public function getStandardAuthorizedStatus(): ?string
    {
        $websiteId = $this->storeManager->getStore()->getWebsiteId();

        return (string)$this->getConfigValue('', ScopeInterface::SCOPE_WEBSITES, (int)$websiteId, self::PAYPLUG_PAYMENT_AUTHORIZED_STATUS_CONFIG_PATH);
    }

    public function getWebsiteOwnerEmail(): ?string
    {
        $websiteId = $this->storeManager->getStore()->getWebsiteId();

        return (string)$this->getConfigValue('', ScopeInterface::SCOPE_WEBSITES, (int)$websiteId, self::EMAIL_WEBSITE_OWNER_CONFIG_PATH);
    }

    /**
     * Get config scope
     */
    public function getConfigScope(): ?string
    {
        return $this->scope;
    }

    /**
     * Get config scope id
     */
    public function getConfigScopeId(): ?int
    {
        return $this->scopeId;
    }

    /**
     * Set API secret key
     */
    public function setPayplugApiKey(?int $storeId, bool $isSandbox, ?string $scope = ScopeInterface::SCOPE_STORE): void
    {
        $key = $this->getApiKey($isSandbox, $storeId, $scope);

        if (!empty($key)) {
            Payplug::init(['secretKey' => $key, 'apiVersion' => '2019-08-06']);
        }
    }

    /**
     * Check if account is connected
     */
    public function isConnected(?string $scope = '', ?int $storeId = null): bool
    {
        $email = $this->getConfigValue('email', $scope, $storeId);
        if ($this->scope == ScopeConfigInterface::SCOPE_TYPE_DEFAULT) {
            return (bool) $email;
        }

        $defaultEmail = $this->getConfigValue('email', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0);

        return !empty($email) && (empty($defaultEmail) || $email !== $defaultEmail);
    }

    /**
     * Retrieve api key
     */
    public function getApiKey(?bool $isSandbox, ?int $storeId = null, ?string $scope = ScopeInterface::SCOPE_STORE): ?string
    {
        return $isSandbox ? $this->getConfigValue('test_api_key', $scope, $storeId) : $this->getConfigValue('live_api_key', $scope, $storeId);
    }

    /**
     * Get is_sandbox flag depending on environment mode
     */
    public function getIsSandbox(?int $store = null): bool
    {
        return (string)$this->getConfigValue('environmentmode', ScopeInterface::SCOPE_STORE, $store) === self::ENVIRONMENT_TEST;
    }

    /**
     * Get is embedded config
     */
    public function isEmbedded(): bool
    {
        return (string)$this->getConfigValue('payment_page') === self::PAYMENT_PAGE_EMBEDDED;
    }

    /**
     * Get is integrated config
     */
    public function isIntegrated(): bool
    {
        return (string)$this->getConfigValue('payment_page') === self::PAYMENT_PAGE_INTEGRATED;
    }

    /**
     * Get one click flag
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
     * Get PayPlug module version
     */
    public function getModuleVersion(): string
    {
        return self::MODULE_VERSION;
    }

    /**
     * Get Magento version
     */
    public function getMagentoVersion(): string
    {
        return $this->productMetadata->getVersion();
    }

    /**
     * Get config value
     */
    public function getConfigValue(string $field, string $scope = ScopeInterface::SCOPE_STORE, ?int $scopeId = null, ?string $path = self::CONFIG_PATH): mixed
    {
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
     */
    public function setConfigValue(string $field, string $value, string $scope = ScopeInterface::SCOPE_STORE, ?int $scopeId = null, ?string $path = null): void
    {
        if ($scopeId === null && $this->scopeId !== null) {
            $scope = $this->scope;
            $scopeId = $this->scopeId;
        }

        if ($path === null) {
            $path = self::CONFIG_PATH;
        }

        $this->configWriter->save( $path . $field, $value, $scope, $scopeId);
    }

    /**
     * Remove payplug config for given scope
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
            // Payplug payment Standard configuration
            'payment/payplug_payments_standard/active',
            'payment/payplug_payments_standard/title',
            'payment/payplug_payments_standard/one_click',
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
        foreach ($pproMethods as $method) {
            $keys = array_merge($keys, [
                'payment/payplug_payments_' . $method . '/active',
                'payment/payplug_payments_' . $method . '/title',
                'payment/payplug_payments_' . $method . '/processing_order_status',
                'payment/payplug_payments_' . $method . '/canceled_order_status',
                'payment/payplug_payments_' . $method . '/allowspecific',
                'payment/payplug_payments_' . $method . '/default_country',
                'payment/payplug_payments_' . $method . '/sort_order',
                'payplug_payments/general/' . $method . '_countries',
                'payplug_payments/general/' . $method . '_min_amounts',
                'payplug_payments/general/' . $method . '_max_amounts',
                'payplug_payments/general/can_use_' . $method,
            ]);
        }

        foreach ($keys as $key) {
            $this->configWriter->delete($key, $this->scope, $this->scopeId);
        }
        $this->systemConfigType->clean();
    }

    /**
     * @param string $isoCode
     * @param int|null $storeId
     * @param string|null $path
     * @param string|null $amountPrefix
     *
     * @return array|bool
     */
    public function getAmountsByCurrency(string $isoCode, ?int $storeId, ?string $path, ?string $amountPrefix = '')
    {
        $minAmounts = [];
        $maxAmounts = [];

        if ($path===null) {
            $path=self::CONFIG_PATH;
        }

        $minAmountsConfig = $this->getConfigValue($amountPrefix . 'min_amounts', ScopeInterface::SCOPE_STORE, $storeId, $path);
        $maxAmountsConfig = $this->getConfigValue($amountPrefix . 'max_amounts', ScopeInterface::SCOPE_STORE, $storeId, $path);

        if (empty($minAmountsConfig) || empty($maxAmountsConfig)) {
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
     * Get config value from database directly
     * Avoids cache issues
     * Make sure to retrive data from specified scope only
     */
    private function getConfigFromDb(string $scope, int $scopeId, string $path): mixed
    {
        $this->adapter = $this->resourceConnection->getConnection();

        $select = $this->adapter->select()
            ->from(
                ['main_table' => $this->adapter->getTableName('core_config_data')],
                'value'
            )
            ->where('main_table.scope like ?', $scope . '%')
            ->where('main_table.scope_id = ?', $scopeId)
            ->where('main_table.path = ?', self::CONFIG_PATH . $path);

        return $this->adapter->fetchOne($select);
    }
}
