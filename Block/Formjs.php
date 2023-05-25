<?php

namespace Payplug\Payments\Block;

use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config;

class Formjs extends \Magento\Framework\View\Element\Template
{
    /**
     * @var Config
     */
    private $helper;

    /**
     * Formjs constructor
     *
     * @param Template\Context $context
     * @param Config           $helper
     * @param array            $data
     */
    public function __construct(Template\Context $context, Config $helper, array $data = [])
    {
        parent::__construct($context, $data);
        $this->helper = $helper;
    }

    /**
     * Get list of external js to include in checkout
     *
     * @return array
     */
    public function getJsUrls(): array
    {
        $urls = [];
        if ($this->isEmbedded()) {
            $urls[] = $this->getPayplugJsUrl();
        }
        if ($this->_scopeConfig->getValue('payment/payplug_payments_apple_pay/active', ScopeInterface::SCOPE_STORE)) {
            $urls[] = 'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js';
        }

        return $urls;
    }

    /**
     * Get PayPlug js url
     *
     * @return string
     */
    public function getPayplugJsUrl()
    {
        $url = $this->getRequest()->getServer('PAYPLUG_API_URL', 'https://api.payplug.com');
        $url .= '/js/1/form.latest.js';

        return $url;
    }

    /**
     * Get embedded option
     *
     * @return bool
     */
    public function isEmbedded()
    {
        return $this->helper->isEmbedded() || $this->helper->isOneClick();
    }

    /**
     * Get PayPlug js url
     *
     * @return string
     */
    public function getPayplugSecureUrl()
    {
        return $this->getRequest()->getServer('PAYPLUG_SECURE_URL', 'https://secure.payplug.com');
    }
}
