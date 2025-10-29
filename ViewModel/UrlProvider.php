<?php

namespace Payplug\Payments\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Service\InitEnvQa;

class UrlProvider implements ArgumentInterface
{
    private const XML_PATH_PAYPLUG_SECURE_URL_KEY = 'payplug_payments/qa/secure_url';
    private const XML_PATH_PAYPLUG_INTEGRATED_PAYMENT_JS_URL_KEY = 'payplug_payments/qa/integrated_payment_js_url';
    private const DEFAULT_SECURE_URL = 'https://secure.payplug.com';
    private const DEFAULT_INTEGRATED_PAYMENT_JS_URL = 'https://cdn.payplug.com/js/integrated-payment/v1/index';
    private const APPLEPAY_SDK_URL = 'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly InitEnvQa $initEnvQa
    ) {
    }

    public function getPayplugSecureUrl(): string
    {
        $customSecureUrl = $this->scopeConfig->getValue(self::XML_PATH_PAYPLUG_SECURE_URL_KEY);

        if ($this->initEnvQa->isQaEnabled() === true && $customSecureUrl) {
            return $customSecureUrl;
        }

        return self::DEFAULT_SECURE_URL;
    }

    public function getPayplugIntegratedPaymentJsUrl(): string
    {
        $customIntegratedPaymentJsUrl = $this->scopeConfig->getValue(self::XML_PATH_PAYPLUG_INTEGRATED_PAYMENT_JS_URL_KEY);

        if ($this->initEnvQa->isQaEnabled() === true && $customIntegratedPaymentJsUrl) {
            return $customIntegratedPaymentJsUrl;
        }

        return self::DEFAULT_INTEGRATED_PAYMENT_JS_URL;
    }

    public function isApplePayEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag('payment/payplug_payments_apple_pay/active', ScopeInterface::SCOPE_STORE);
    }

    public function getApplePaySdkUrl(): string
    {
        return self::APPLEPAY_SDK_URL;
    }
}
