<?php

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
use Payplug\Payments\Model\Api\Login;
use Payplug\Payplug;

class Config extends AbstractHelper
{
    public const CONFIG_PATH = 'payplug_payments/general/';
    public const ONEY_CONFIG_PATH = 'payment/payplug_payments_oney/';
    public const ONEY_WITHOUT_FEES_CONFIG_PATH = 'payment/payplug_payments_oney_without_fees/';

    public const ENVIRONMENT_TEST = 'test';
    public const ENVIRONMENT_LIVE = 'live';
    public const PAYMENT_PAGE_REDIRECT = 'redirect';
    public const PAYMENT_PAGE_EMBEDDED = 'embedded';
    public const PAYMENT_PAGE_INTEGRATED = 'integrated';

    public const MODULE_VERSION = '4.0.0';

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var System
     */
    private $systemConfigType;

    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * @var AdapterInterface
     */
    private $resourceConnection;

    /**
     * @var Login
     */
    private $login;

    /**
     * @var string
     */
    private $scope;

    /**
     * @var mixed
     */
    private $scopeId;

    /**
     * @param Context                  $context
     * @param WriterInterface          $configWriter
     * @param System                   $systemConfigType
     * @param ProductMetadataInterface $productMetadata
     * @param ResourceConnection       $resourceConnection
     * @param Login                    $login
     */
    public function __construct(
        Context $context,
        WriterInterface $configWriter,
        System $systemConfigType,
        ProductMetadataInterface $productMetadata,
        ResourceConnection $resourceConnection,
        Login $login
    ) {
        parent::__construct($context);
        $this->configWriter = $configWriter;
        $this->systemConfigType = $systemConfigType;
        $this->productMetadata = $productMetadata;
        $this->resourceConnection = $resourceConnection->getConnection();
        $this->login = $login;
    }

    /**
     * Init BO config scope
     */
    public function initScopeData()
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
        $this->scopeId = $scopeId;
    }

    /**
     * Get config scope
     *
     * @return string|null
     */
    public function getConfigScope()
    {
        return $this->scope;
    }

    /**
     * Get config scope id
     *
     * @return int|null
     */
    public function getConfigScopeId()
    {
        return $this->scopeId;
    }

    /**
     * Set API secret key
     *
     * @param int  $storeId
     * @param bool $isSandbox
     */
    public function setPayplugApiKey($storeId, $isSandbox)
    {
        $key = $this->getApiKey($isSandbox, $storeId);

        if (!empty($key)) {
            Payplug::init(['secretKey' => $key, 'apiVersion' => '2019-08-06']);
        }
    }

    /**
     * Check if account is connected
     *
     * @return bool
     */
    public function isConnected()
    {
        $email = $this->getConfigValue('email');
        if ($this->scope == ScopeConfigInterface::SCOPE_TYPE_DEFAULT) {
            return (bool) $email;
        }

        $defaultEmail = $this->getConfigValue('email', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0);

        return !empty($email) && (empty($defaultEmail) || $email !== $defaultEmail);
    }

    /**
     * Retrieve api key
     *
     * @param $storeId
     * @param $isSandbox
     *
     * @return string|null
     */
    public function getApiKey($isSandbox, $storeId = null)
    {
        if ($isSandbox) {
            return $this->getConfigValue('test_api_key', ScopeInterface::SCOPE_STORE, $storeId);
        }

        return $this->getConfigValue('live_api_key', ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Get is_sandbox flag depending on environment mode
     *
     * @param int $store
     *
     * @return bool
     */
    public function getIsSandbox($store = null)
    {
        $environmentMode = $this->getConfigValue('environmentmode', ScopeInterface::SCOPE_STORE, $store);

        return $environmentMode == self::ENVIRONMENT_TEST;
    }

    /**
     * Get is embedded config
     *
     * @return bool
     */
    public function isEmbedded()
    {
        return $this->getConfigValue('payment_page') == self::PAYMENT_PAGE_EMBEDDED;
    }

    /**
     * Get is integrated config
     *
     * @return bool
     */
    public function isIntegrated()
    {
        return $this->getConfigValue('payment_page') == self::PAYMENT_PAGE_INTEGRATED;
    }

    /**
     * Get one click flag
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isOneClick($storeId = null)
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
     *
     * @return string
     */
    public function getModuleVersion()
    {
        return self::MODULE_VERSION;
    }

    /**
     * Get Magento version
     *
     * @return string
     */
    public function getMagentoVersion()
    {
        return $this->productMetadata->getVersion();
    }

    /**
     * Get config value
     *
     * @param string      $field
     * @param string      $scope
     * @param string|null $scopeId
     * @param string|null $path
     *
     * @return mixed
     */
    public function getConfigValue(
        $field,
        $scope = ScopeInterface::SCOPE_STORE,
        $scopeId = null,
        $path = self::CONFIG_PATH
    ) {
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
     * @param string      $field
     * @param string      $value
     * @param string      $scope
     * @param string|null $scopeId
     *
     * @return void
     */
    public function setConfigValue($field, $value, $scope = ScopeInterface::SCOPE_STORE, $scopeId = null, $path = null)
    {
        if ($scopeId === null && $this->scopeId !== null) {
            $scope = $this->scope;
            $scopeId = $this->scopeId;
        }

        if($path === null){
            $path = self::CONFIG_PATH;
        }

        $this->configWriter->save( $path . $field, $value, $scope, $scopeId);
    }

    /**
     * Remove payplug config for given scope
     */
    public function clearConfig()
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
     * Get valid range of amount for a given currency
     *
     * @param string $isoCode
     * @param int    $storeId
     * @param string $amountPrefix
     * @param string $path
     *
     * @return bool|array
     */
    public function getAmountsByCurrency($isoCode, $storeId, $path, $amountPrefix = '')
    {
        $minAmounts = [];
        $maxAmounts = [];

        if($path===null){
            $path=self::CONFIG_PATH;
        }

        $minAmountsConfig = $this->getConfigValue($amountPrefix . 'min_amounts', ScopeInterface::SCOPE_STORE, $storeId, $path);
        $maxAmountsConfig = $this->getConfigValue($amountPrefix . 'max_amounts', ScopeInterface::SCOPE_STORE, $storeId, $path);

        if(empty($minAmountsConfig) || empty($maxAmountsConfig)){
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
     *
     * @param string $scope
     * @param int    $scopeId
     * @param string $path
     *
     * @return mixed
     */
    private function getConfigFromDb(string $scope, int $scopeId, string $path)
    {
        $select = $this->resourceConnection->select()
            ->from(
                ['main_table' => $this->resourceConnection->getTableName('core_config_data')],
                'value'
            )
            ->where('main_table.scope like ?', $scope . '%')
            ->where('main_table.scope_id = ?', $scopeId)
            ->where('main_table.path = ?', self::CONFIG_PATH . $path);

        return $this->resourceConnection->fetchOne($select);
    }
}
