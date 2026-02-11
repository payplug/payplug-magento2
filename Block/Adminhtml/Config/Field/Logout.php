<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Block\Adminhtml\Config\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config;

class Logout extends Field
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
     * Retrieve element HTML markup
     *
     * @param AbstractElement $element
     *
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $this->helper->initScopeData();

        /** @var Button $buttonBlock  */
        $buttonBlock = $this->getForm()->getLayout()->createBlock(Button::class);

        $data = [
            'id' => 'payplug_payments_disconnectUrl',
            'label' => __('Disconnect'),
            'onclick' => "setLocation('" . $this->getButtonUrl() . "')"
        ];

        $html = $buttonBlock->setData($data)->toHtml();

        return $html;
    }

    /**
     * Get button url according to current scope
     *
     * @return string
     */
    private function getButtonUrl(): string
    {
        $parameters = [];
        $scope = $this->helper->getConfigScope();
        $scopeId = $this->helper->getConfigScopeId();
        if ($scope == ScopeInterface::SCOPE_STORES) {
            $parameters['store'] = $scopeId;
        }
        if ($scope == ScopeInterface::SCOPE_WEBSITES) {
            $parameters['website'] = $scopeId;
        }

        $parameters['form_key'] = $this->formKey->getFormKey() ?: '';

        return $this->getUrl('payplug_payments_admin/config/logout', $parameters);
    }

    /**
     * Return false to hide inherit checkbox
     *
     * @param mixed $element
     * @return false
     */
    protected function _isInheritCheckboxRequired($element): bool
    {
        return false;
    }

    /**
     * Render block HTML
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        return parent::render($element);
    }
}
