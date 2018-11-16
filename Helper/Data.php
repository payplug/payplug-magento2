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
use Payplug\Payments\Model\OrderPaymentRepository;
use Payplug\Payments\Model\PaymentMethod;
use Payplug\Resource\Payment;

class Data extends AbstractHelper
{
    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var string
     */
    private $scope;

    /**
     * @var mixed
     */
    private $scopeId;

    /**
     * @var System
     */
    private $systemConfigType;

    /**
     * @var \Payplug\Payments\Model\Order\PaymentFactory
     */
    protected $paymentFactory;

    /**
     * @var OrderPaymentRepository
     */
    protected $orderPaymentRepository;

    /**
     * @var ModuleListInterface
     */
    protected $moduleList;

    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @param Context                                      $context
     * @param WriterInterface                              $configWriter
     * @param System                                       $systemConfigType
     * @param \Payplug\Payments\Model\Order\PaymentFactory $paymentFactory
     * @param OrderPaymentRepository                       $orderPaymentRepository
     * @param ModuleListInterface                          $moduleList
     * @param ProductMetadataInterface                     $productMetadata
     */
    public function __construct(
        Context $context,
        WriterInterface $configWriter,
        System $systemConfigType,
        \Payplug\Payments\Model\Order\PaymentFactory $paymentFactory,
        OrderPaymentRepository $orderPaymentRepository,
        ModuleListInterface $moduleList,
        ProductMetadataInterface $productMetadata
    ) {
        parent::__construct($context);
        $this->configWriter = $configWriter;
        $this->systemConfigType = $systemConfigType;
        $this->paymentFactory = $paymentFactory;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->moduleList = $moduleList;
        $this->productMetadata = $productMetadata;
    }

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
     * @return mixed
     */
    public function getConfigScope()
    {
        return $this->scope;
    }

    /**
     * @return mixed
     */
    public function getConfigScopeId()
    {
        return $this->scopeId;
    }

    /**
     * @param string $field
     *
     * @return mixed
     */
    public function getAdminConfigValue($field)
    {
        return $this->getConfigValue($field, $this->scope, $this->scopeId);
    }

    /**
     * @param string $field
     * @param string $value
     */
    public function setAdminConfigValue($field, $value)
    {
        $this->setConfigValue($field, $value, $this->scope, $this->scopeId);
    }

    /**
     * @param string      $field
     * @param string      $scope
     * @param string|null $scopeId
     *
     * @return mixed
     */
    public function getConfigValue($field, $scope = ScopeInterface::SCOPE_STORE, $scopeId = null)
    {
        return $this->scopeConfig->getValue(
            'payment/payplug_payments/' . $field,
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
        $this->configWriter->save('payment/payplug_payments/' . $field, $value, $scope, $scopeId);
    }

    /**
     * Check if account is connected
     *
     * @return bool
     */
    public function isConnected()
    {
        $email = $this->getAdminConfigValue('email');
        if ($this->scope == ScopeConfigInterface::SCOPE_TYPE_DEFAULT) {
            return (bool) $email;
        }

        $defaultEmail = $this->getConfigValue('email', ScopeConfigInterface::SCOPE_TYPE_DEFAULT);

        return !empty($email) && (empty($defaultEmail) || $email !== $defaultEmail);
    }

    /**
     * Remove payplug config for given scope
     */
    public function clearConfig()
    {
        $keys = [
            'test_api_key',
            'live_api_key',
            'connected',
            'currencies',
            'min_amounts',
            'max_amounts',
            'company_id',
            'premium',
            'verified',
            'email',
            'pwd',
            'environmentmode',
            'active',
            'payment_page',
            'one_click',
            'generate_invoice',
            'allow_specific',
            'specificcountry',
        ];

        foreach ($keys as $key) {
            $this->configWriter->delete('payment/payplug_payments/' . $key, $this->scope, $this->scopeId);
        }
        $this->systemConfigType->clean();
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

        return $environmentMode == PaymentMethod::ENVIRONMENT_TEST;
    }

    /**
     * @param int $orderId
     *
     * @return \Payplug\Payments\Model\Order\Payment
     */
    public function getOrderPayment($orderId)
    {
        return $this->orderPaymentRepository->get($orderId, 'order_id');
    }

    /**
     * @param Payment $payment
     *
     * @return string
     */
    public function getPaymentErrorMessage($payment)
    {
        if ($payment->failure === null) {
            return '';
        }

        if ($payment->failure->message) {
            return $payment->failure->message;
        }

        return '';
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
     * @return mixed
     */
    public function getAccountEmail()
    {
        return $this->getConfigValue('email', ScopeInterface::SCOPE_STORE, $this->scopeId);
    }
}
