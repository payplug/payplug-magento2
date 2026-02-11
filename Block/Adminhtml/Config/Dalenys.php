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
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\Js;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config;

class Dalenys extends Fieldset
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
        $legacyConnected = $this->helper->isLegacyConnected(
            ScopeInterface::SCOPE_WEBSITE,
            (int)$this->getRequest()->getParam('website')
        );
        $oauthConnected = $this->helper->isOauthConnected(
            ScopeInterface::SCOPE_WEBSITE,
            (int)$this->getRequest()->getParam('website')
        );

        if ($legacyConnected === true || $oauthConnected === true) {
            return parent::render($element);
        }

        $element->setData('comment', __(
            'Login to Dalenys GM is only accessible after initiating a Standard or OAuth2 connection to Payplug'
        ));

        foreach ($element->getElements() as $field) {
            if ($field->getId() === 'payplug_payments_dalenys_gm_identifier') {
                continue;
            }

            $element->removeField($field->getId());
        }

        return parent::render($element);
    }
}
