<?php

namespace Payplug\Payments\Block\Adminhtml\Config;

use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Data;

class Logout extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var string
     */
    private $scope;

    /**
     * @var string
     */
    private $scopeId;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param Data                                    $helper
     * @param array                                   $data
     */
    public function __construct(\Magento\Backend\Block\Template\Context $context, Data $helper, array $data = [])
    {
        $this->helper = $helper;
        parent::__construct($context, $data);
    }

    /**
     * Retrieve element HTML markup
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $this->initScopeData();

        /** @var \Magento\Backend\Block\Widget\Button $buttonBlock  */
        $buttonBlock = $this->getForm()->getLayout()->createBlock(\Magento\Backend\Block\Widget\Button::class);

        $data = [
            'id' => 'payplug_payments_disconnectUrl',
            'label' => $element->getLabel(),
            'onclick' => "setLocation('" . $this->getButtonUrl() . "')",
        ];

        $html = $buttonBlock->setData($data)->toHtml();
        return $html;
    }

    /**
     * @return string
     */
    private function getButtonUrl()
    {
        $parameters = [];
        if ($this->scope == ScopeInterface::SCOPE_STORES) {
            $parameters['store'] = $this->scopeId;
        }
        if ($this->scope == ScopeInterface::SCOPE_WEBSITES) {
            $parameters['website'] = $this->scopeId;
        }

        $disconnectUrl = $this->getUrl('payplug_payments_admin/config/logout', $parameters);

        return $disconnectUrl;
    }

    private function initScopeData()
    {
        $this->helper->initScopeData();
        $this->scope = $this->helper->getConfigScope();
        $this->scopeId = $this->helper->getConfigScopeId();
    }
}
