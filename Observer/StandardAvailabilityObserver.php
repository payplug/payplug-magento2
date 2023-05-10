<?php

namespace Payplug\Payments\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Model\Method\Adapter;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManager;
use Payplug\Payments\Gateway\Config\ApplePay;
use Payplug\Payments\Gateway\Config\InstallmentPlan;
use Payplug\Payments\Gateway\Config\Oney;
use Payplug\Payments\Gateway\Config\OneyWithoutFees;
use Payplug\Payments\Gateway\Config\Standard;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\Api\Login;

class StandardAvailabilityObserver implements ObserverInterface
{
    /**
     * @var Config
     */
    private $payplugConfig;

    /**
     * @var Data
     */
    private $payplugHelper;

    /**
     * @var Login
     */
    private $login;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var StoreManager
     */
    private $storeManager;

    /**
     * @param Config       $payplugConfig
     * @param Data         $payplugHelper
     * @param Login        $login
     * @param Logger       $logger
     * @param StoreManager $storeManager
     */
    public function __construct(
        Config $payplugConfig,
        Data $payplugHelper,
        Login $login,
        Logger $logger,
        StoreManager $storeManager
    ) {
        $this->payplugConfig = $payplugConfig;
        $this->payplugHelper = $payplugHelper;
        $this->login = $login;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
    }

    /**
     * Check if PayPlug payment can be used on quote
     *
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var CartInterface $quote */
        $quote = $observer->getData('quote');
        if ($quote === null) {
            return;
        }
        /** @var DataObject $checkResult */
        $checkResult = $observer->getData('result');
        /** @var Adapter $adapter */
        $adapter = $observer->getData('method_instance');

        if (!$this->payplugHelper->isCodePayplugPayment($adapter->getCode())) {
            return;
        }

        $storeId = $quote->getStoreId();

        if (!$adapter->getConfigData('active', $storeId)) {
            $checkResult->setData('is_available', false);
            return;
        }

        $testApiKey = $this->payplugConfig->getConfigValue(
            'test_api_key',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $liveApiKey = $this->payplugConfig->getConfigValue(
            'live_api_key',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if (empty($testApiKey) && empty($liveApiKey)) {
            $checkResult->setData('is_available', false);
            return;
        }

        $apiKey = $liveApiKey;
        $environmentMode = $this->payplugConfig->getConfigValue(
            'environmentmode',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if ($environmentMode == Config::ENVIRONMENT_TEST) {
            $apiKey = $testApiKey;
        }

        $prefix = '';
        if ($adapter->getCode() == Oney::METHOD_CODE || $adapter->getCode() == OneyWithoutFees::METHOD_CODE) {
            $prefix = 'oney_';
        }

        $currency = $quote->getCurrency()->getQuoteCurrencyCode();
        $amountsByCurrency = $this->payplugConfig->getAmountsByCurrency($currency, $quote->getStoreId(), $prefix);
        if ($amountsByCurrency === false) {
            $checkResult->setData('is_available', false);
            return;
        }

        // Oney can be displayed (disabled) in checkout
        // Do not check amount to confirm validity
        if ($adapter->getCode() == Oney::METHOD_CODE || $adapter->getCode() == OneyWithoutFees::METHOD_CODE) {
            $canUseOney = $this->payplugConfig->getConfigValue(
                'can_use_oney',
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            if (!$canUseOney) {
                $checkResult->setData('is_available', false);
            }
            if ($adapter->getCode() == OneyWithoutFees::METHOD_CODE) {
                $isOneyWithFeesActive = $this->payplugConfig->getConfigValue(
                    'active',
                    ScopeInterface::SCOPE_STORE,
                    $storeId,
                    'payment/payplug_payments_oney/'
                );
                // If oney with fees is already enabled, make oney without fees unavailable
                if ($isOneyWithFeesActive) {
                    $checkResult->setData('is_available', false);
                }
            }

            return;
        }

        if ($adapter->getCode() == InstallmentPlan::METHOD_CODE) {
            $canCreateInstallmentPlan = $this->payplugConfig->getConfigValue(
                'can_create_installment_plan',
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            if (!$canCreateInstallmentPlan) {
                $checkResult->setData('is_available', false);

                return;
            }
        }

        if ($adapter->getCode() === ApplePay::METHOD_CODE) {
            $onboardDomains = [];
            try {
                $result = $this->login->getAccount($apiKey);
                $onboardDomains = $result['answer']['payment_methods']['apple_pay']['allowed_domain_names'] ?? [];
            } catch (\Exception $e) {
                $this->logger->error('Could not retrieve Payplug account data', [
                    'exception' => $e,
                ]);
            }

            $merchandDomain = parse_url($this->payplugConfig->getConfigValue(
                'base_url',
                ScopeInterface::SCOPE_STORE,
                $storeId,
                'web/secure/'
            ), PHP_URL_HOST);
            if (!in_array($merchandDomain, $onboardDomains)) {
                $this->logger->error('Payplug ApplePay is not available for this domain. It will be hidden from available payment methods in checkout.', [
                    'merchant_domain' => $merchandDomain,
                ]);
                $checkResult->setData('is_available', false);

                return;
            }
        }

        $amount = (int) round($quote->getGrandTotal() * 100);

        if ($amount < $amountsByCurrency['min_amount'] || $amount > $amountsByCurrency['max_amount']) {
            $checkResult->setData('is_available', false);
            return;
        }

        if ($adapter->getCode() == InstallmentPlan::METHOD_CODE) {
            if ($quote->getGrandTotal() < $adapter->getConfigData('threshold')) {
                $checkResult->setData('is_available', false);
                return;
            }
        }

        if ($adapter->getCode() == Standard::METHOD_CODE) {
            $this->payplugConfig->handleIntegratedPayment($this->storeManager->getWebsite()->getId());
        }
    }
}
