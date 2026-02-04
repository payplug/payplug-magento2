<?php

namespace Payplug\Payments\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

class UrlProvider implements ArgumentInterface
{
    private const SECURE_URL = 'https://secure.payplug.com';
    private const INTEGRATED_PAYMENT_JS_URL = 'https://cdn.payplug.com/js/integrated-payment/v1/index';
    private const APPLEPAY_SDK_URL = 'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
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
        return self::INTEGRATED_PAYMENT_JS_URL;
    }

    /**
     * Get Payplug init env qa url
     *
     * @return bool
     */
    public function isApplePayEnabled(): bool
    {
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
