<?php

declare(strict_types=1);

namespace Payplug\Payments\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Service\GetApiRouteByKey;

class Formjs extends Template
{
    private const PAYPLUG_SECURE_URL = 'https://secure.payplug.com';
    private const APPLEPAY_SDK_URL = 'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js';

    public function __construct(
        private readonly GetApiRouteByKey $getApiRouteByKey,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getJsUrls(): array
    {
        $urls = [];
        if ($this->_scopeConfig->getValue('payment/payplug_payments_apple_pay/active', ScopeInterface::SCOPE_STORE)) {
            $urls[] = self::APPLEPAY_SDK_URL;
        }

        return $urls;
    }

    public function getPayplugSecureUrl(): string
    {
        return $this->getApiRouteByKey->execute(
            'PAYPLUG_SECURE_URL',
            self::PAYPLUG_SECURE_URL
        );
    }
}
