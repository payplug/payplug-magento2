<?php

declare(strict_types=1);

namespace Payplug\Payments\Block\Adminhtml\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config;

class LegacyAuthInfo extends Field
{
    public function __construct(
        Context $context,
        private Config $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $isLegacyConnected = $this->helper->isLegacyConnected(ScopeInterface::SCOPE_WEBSITE, (int)$this->_request->getParam('website'));
        $isOauthConnected = $this->helper->isOauthConnected(ScopeInterface::SCOPE_WEBSITE, (int)$this->_request->getParam('website'));

        if ($isLegacyConnected) {
            $message = __('You are currently connected');
        } elseif ($isOauthConnected) {
            $message = __('You are currently connected with OAuth Authentication');
        } else {
            $message = __('You are currently not connected');
        }

        return  $message->render();
    }
}
