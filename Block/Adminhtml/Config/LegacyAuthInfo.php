<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Block\Adminhtml\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config;

class LegacyAuthInfo extends Field
{
    /**
     * @param Config $helper
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        private readonly Config $helper,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Retrieve element HTML markup
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $isLegacyConnected = $this->helper->isLegacyConnected(
            ScopeInterface::SCOPE_WEBSITE,
            (int)$this->_request->getParam('website')
        );

        $isOauthConnected = $this->helper->isOauthConnected(
            ScopeInterface::SCOPE_WEBSITE,
            (int)$this->_request->getParam('website')
        );

        if ($isLegacyConnected) {
            $message = __('Connected with <strong>%1</strong>', $this->helper->getConfigValue('email'));
        } elseif ($isOauthConnected) {
            $message = __('Connected with OAuth2 Authentication');
        } else {
            $message = __('Not connected');
        }

        return  $message->render();
    }
}
