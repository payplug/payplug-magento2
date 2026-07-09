<?php

namespace Payplug\Payments\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

class UrlProvider implements ArgumentInterface
{
    private const SECURE_URL = 'https://secure.payplug.com';
    private const INTEGRATED_PAYMENT_JS_PATH = 'https://cdn.payplug.com/js/integrated-payment/v1/index';
    private const HF_PAYMENT_JS_PATH = 'https://js.dalenys.com/hosted-fields/v2.2.0/hosted-fields.min';
    private const APPLEPAY_SDK_URL = 'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js';
    private const PAYPLUG_RETAIL_BASE_CURRENCY_CODE = 'EUR';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Get Payplug secure url
     *
     * @return string
     */
    public function getPayplugSecureUrl(): string
    {
        return self::SECURE_URL;
    }

    /**
     * Get Payplug integrated payment js url
     *
     * @return string
     */
    public function getPayplugIntegratedPaymentJsUrl(): string
    {
        return self::INTEGRATED_PAYMENT_JS_PATH;
    }

    /**
     * Get Payplug integrated payment js url
     *
     * @return string
     */
    public function getPayplugHostedFieldsJsUrl(): string
    {
        return self::HF_PAYMENT_JS_PATH;
    }

    /**
     * Get Payplug init env qa url
     *
     * @return bool
     */
    public function isApplePayEnabled(): bool
    {
        try {
            $baseCurrencyCode = $this->storeManager->getWebsite()->getBaseCurrencyCode();
        } catch (Throwable) {
            return false;
        }

        if ($baseCurrencyCode !== self::PAYPLUG_RETAIL_BASE_CURRENCY_CODE) {
            return false;
        }

        return $this->scopeConfig->isSetFlag('payment/payplug_payments_apple_pay/active', ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get Apple Pay SDK url
     *
     * @return string
     */
    public function getApplePaySdkUrl(): string
    {
        return self::APPLEPAY_SDK_URL;
    }
}
