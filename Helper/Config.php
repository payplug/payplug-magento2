<?php

namespace Payplug\Payments\Helper;

use Magento\Config\App\Config\Type\System;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payplug;

class Config extends AbstractHelper
{
    const CONFIG_PATH = 'payplug_payments/general/';

    const ENVIRONMENT_TEST = 'test';
    const ENVIRONMENT_LIVE = 'live';
    const PAYMENT_PAGE_REDIRECT = 'redirect';
    const PAYMENT_PAGE_EMBEDDED = 'embedded';

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var System
     */
    private $systemConfigType;

    /**
     * @var ModuleListInterface
     */
    private $moduleList;

    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

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
     * @param ModuleListInterface      $moduleList
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(
        Context $context,
        WriterInterface $configWriter,
        System $systemConfigType,
        ModuleListInterface $moduleList,
        ProductMetadataInterface $productMetadata
    ) {
        parent::__construct($context);
        $this->configWriter = $configWriter;
        $this->systemConfigType = $systemConfigType;
        $this->moduleList = $moduleList;
        $this->productMetadata = $productMetadata;
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
     * @return string|null
     */
    public function getConfigScope()
    {
        return $this->scope;
    }

    /**
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
        if ($isSandbox) {
            $key = $this->getConfigValue('test_api_key', ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            $key = $this->getConfigValue('live_api_key', ScopeInterface::SCOPE_STORE, $storeId);
        }

        if (!empty($key)) {
            Payplug::setSecretKey($key);
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
     * @return bool
     */
    public function isEmbedded()
    {
        return $this->getConfigValue('payment_page') == self::PAYMENT_PAGE_EMBEDDED;
    }

    /**
     * @return string
     */
    public function getModuleVersion()
    {
        return $this->moduleList->getOne('Payplug_Payments')['setup_version'];
    }

    /**
     * @return string
     */
    public function getMagentoVersion()
    {
        return $this->productMetadata->getVersion();
    }

    /**
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
     * @param string      $field
     * @param string      $value
     * @param string      $scope
     * @param string|null $scopeId
     *
     * @return void
     */
    public function setConfigValue($field, $value, $scope = ScopeInterface::SCOPE_STORE, $scopeId = null)
    {
        if ($scopeId === null && $this->scopeId !== null) {
            $scope = $this->scope;
            $scopeId = $this->scopeId;
        }

        $this->configWriter->save(self::CONFIG_PATH . $field, $value, $scope, $scopeId);
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
            // Payplug payment Standard configuration
            'payment/payplug_payments_standard/active',
            'payment/payplug_payments_standard/title',
            'payment/payplug_payments_standard/one_click',
            'payment/payplug_payments_standard/processing_order_status',
            'payment/payplug_payments_standard/canceled_order_status',
            'payment/payplug_payments_standard/allow_specific',
            'payment/payplug_payments_standard/specificcountry',
            // Payplug payment Installment plan configuration
            'payment/payplug_payments_installment_plan/active',
            'payment/payplug_payments_installment_plan/title',
            'payment/payplug_payments_installment_plan/count',
            'payment/payplug_payments_installment_plan/threshold',
            'payment/payplug_payments_installment_plan/processing_order_status',
            'payment/payplug_payments_installment_plan/canceled_order_status',
            'payment/payplug_payments_installment_plan/allow_specific',
            'payment/payplug_payments_installment_plan/specificcountry',
        ];

        foreach ($keys as $key) {
            $this->configWriter->delete($key, $this->scope, $this->scopeId);
        }
        $this->systemConfigType->clean();
    }
}
