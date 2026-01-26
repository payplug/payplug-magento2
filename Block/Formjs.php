<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config;

class Formjs extends Template
{
    /**
     * @param Context $context
     * @param Config $helper
     * @param array $data
     */
    public function __construct(
        Context $context,
        private Config $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get list of external js to include in checkout
     *
     * @return array
     */
    public function getJsUrls(): array
    {
        $urls = [];
        if ($this->_scopeConfig->getValue('payment/payplug_payments_apple_pay/active', ScopeInterface::SCOPE_STORE)) {
            $urls[] = 'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js';
        }

        return $urls;
    }

    /**
     * Get PayPlug js url
     *
     * @return string
     */
    public function getPayplugSecureUrl(): string
    {
        return $this->getRequest()->getServer('PAYPLUG_SECURE_URL', 'https://secure.payplug.com');
    }
}
