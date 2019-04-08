<?php

namespace Payplug\Payments\Block\Adminhtml\Config;

use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config;

class Logout extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @var Config
     */
    private $helper;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param Config                                  $helper
     * @param array                                   $data
     */
    public function __construct(\Magento\Backend\Block\Template\Context $context, Config $helper, array $data = [])
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
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $this->helper->initScopeData();

        /** @var \Magento\Backend\Block\Widget\Button $buttonBlock  */
        $buttonBlock = $this->getForm()->getLayout()->createBlock(\Magento\Backend\Block\Widget\Button::class);

        $html = '<p>' . $this->helper->getConfigValue('email') . '</p>';
        $data = [
            'id' => 'payplug_payments_disconnectUrl',
            'label' => $element->getLabel(),
            'onclick' => "setLocation('" . $this->getButtonUrl() . "')",
        ];

        $html .= $buttonBlock->setData($data)->toHtml();
        return $html;
    }

    /**
     * @return string
     */
    private function getButtonUrl()
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

        $disconnectUrl = $this->getUrl('payplug_payments_admin/config/logout', $parameters);

        return $disconnectUrl;
    }
}
