<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Observer;

use Laminas\Uri\Http as UriHelper;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Model\Method\Adapter;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Gateway\Config\ApplePay;
use Payplug\Payments\Gateway\Config\InstallmentPlan;
use Payplug\Payments\Gateway\Config\Oney;
use Payplug\Payments\Gateway\Config\OneyWithoutFees;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\Api\Login;

class StandardAvailabilityObserver implements ObserverInterface
{
    /**
     * @param Config $payplugConfig
     * @param Data $payplugHelper
     * @param Login $login
     * @param Logger $logger
     * @param UriHelper $uriHelper
     */
    public function __construct(
        private readonly Config $payplugConfig,
        private readonly Data $payplugHelper,
        private readonly Login $login,
        private readonly Logger $logger,
        private readonly UriHelper $uriHelper
    ) {
    }

    /**
     * Check if PayPlug payment can be used on quote
     *
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer): void
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

        $storeId = (int)$quote->getStoreId();
        if (!$adapter->getConfigData('active', $storeId)) {
            $checkResult->setData('is_available', false);
            return;
        }
        $environmentMode = $this->payplugConfig->getConfigValue(
            Config::OAUTH_ENVIRONMENT_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $isSandbox = ($environmentMode === Config::ENVIRONMENT_TEST);

        // Helper hands back either legacy test/live key or OAuth2 token
        $apiKey = $this->payplugConfig->getApiKey($isSandbox, $storeId);
        if (empty($apiKey)) {
            $checkResult->setData('is_available', false);

            return;
        }

        $prefix = '';
        $path = Config::CONFIG_PATH;
        if ($adapter->getCode() == Oney::METHOD_CODE || $adapter->getCode() == OneyWithoutFees::METHOD_CODE) {
            $prefix = 'oney_';
            $path = Config::ONEY_CONFIG_PATH;
        } elseif ($this->payplugHelper->isCodePayplugPaymentPpro($adapter->getCode())) {
            $prefix = str_replace('payplug_payments_', '', $adapter->getCode()) . '_';
        }

        $currency = $quote->getCurrency()->getQuoteCurrencyCode();
        $amountsByCurrency = $this->payplugConfig->getAmountsByCurrency(
            $currency,
            (int)$quote->getStoreId(),
            $path,
            $prefix
        );

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

            $baseUrl = $this->payplugConfig->getConfigValue(
                'base_url',
                ScopeInterface::SCOPE_STORE,
                $storeId,
                'web/secure/'
            );
            $merchandDomain = $this->uriHelper->parse($baseUrl)->getHost();

            if (!in_array($merchandDomain, $onboardDomains)) {
                $this->logger->error('Payplug ApplePay is not available for this domain. ' .
                    'It will be hidden from available payment methods in checkout.', [
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
    }
}
