<?php

namespace Payplug\Payments\Model\System\Message;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Notification\MessageInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Payplug\Payments\Helper\Config;

class PayplugConfigMessage implements MessageInterface
{
    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param UrlInterface          $urlBuilder
     * @param ScopeConfigInterface  $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        UrlInterface $urlBuilder,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritDoc
     */
    public function getIdentity()
    {
        return md5('PAYPLUG_CONFIG_MESSAGE');
    }

    /**
     * @inheritDoc
     */
    public function isDisplayed()
    {
        if ($this->isConfigMissing(ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null)) {
            return true;
        }

        foreach ($this->storeManager->getWebsites() as $website) {
            if ($this->isConfigMissing(ScopeInterface::SCOPE_WEBSITES, $website->getCode())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $scopeType
     * @param null   $scopeCode
     *
     * @return bool
     */
    private function isConfigMissing(string $scopeType, $scopeCode = null): bool
    {
        $testApiKey = $this->scopeConfig->getValue(Config::CONFIG_PATH . 'test_api_key', $scopeType, $scopeCode);
        $liveApiKey = $this->scopeConfig->getValue(Config::CONFIG_PATH . 'live_api_key', $scopeType, $scopeCode);

        if (empty($testApiKey) && empty($liveApiKey)) {
            // Payplug has never been configured, no need to display message
            return false;
        }

        $canUseOney = $this->scopeConfig->getValue(Config::CONFIG_PATH . 'can_use_oney', $scopeType, $scopeCode);
        if ($canUseOney === null || $canUseOney === '') {
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function getText()
    {
        $message = __('You just updated your PayPlug module.') . ' ';
        $url = $this->urlBuilder->getUrl('adminhtml/system_config/edit/section/payplug_payments');
        $message .= __('Please go to <a href="%1">PayPlug configuration section</a> and save your configuration again.', $url);

        return $message;
    }

    /**
     * @inheritDoc
     */
    public function getSeverity()
    {
        return self::SEVERITY_MAJOR;
    }
}
