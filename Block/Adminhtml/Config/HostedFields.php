<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Block\Adminhtml\Config;

use Magento\Backend\Block\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Config\Block\System\Config\Form\Fieldset;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\Js;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Payplug\Payments\Helper\Config;

class HostedFields extends Fieldset
{
    /**
     * @param Config $helper
     * @param Context $context
     * @param Session $authSession
     * @param Js $jsHelper
     * @param array $data
     * @param SecureHtmlRenderer|null $secureRenderer
     */
    public function __construct(
        private readonly Config $helper,
        Context $context,
        Session $authSession,
        Js $jsHelper,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        parent::__construct($context, $authSession, $jsHelper, $data, $secureRenderer);
    }

    /**
     * Render element
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $isDefaultLegacyConnected = (bool) $this->helper->getConfigValue(
            'email',
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            Store::DEFAULT_STORE_ID
        );

        $isDefaultOauth2Connected = (bool) $this->helper->getConfigValue(
            'email',
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            Store::DEFAULT_STORE_ID,
            Config::OAUTH_CONFIG_PATH
        );

        $isWebsiteLegacyConnected = $this->helper->isLegacyConnected(
            ScopeInterface::SCOPE_WEBSITE,
            (int)$this->getRequest()->getParam('website')
        );

        $isWebsiteOauthConnected = $this->helper->isOauthConnected(
            ScopeInterface::SCOPE_WEBSITE,
            (int)$this->getRequest()->getParam('website')
        );

        if ($isDefaultLegacyConnected === true || $isDefaultOauth2Connected === true
            || $isWebsiteLegacyConnected === true || $isWebsiteOauthConnected === true
        ) {
            return parent::render($element);
        }

        $element->setData('comment', __(
            'Hosted Fields Advanced is only accessible after initiating a Standard or OAuth2 connection to Payplug'
        ));

        foreach ($element->getElements() as $field) {
            $element->removeField($field->getId());
        }

        return parent::render($element);
    }
}
