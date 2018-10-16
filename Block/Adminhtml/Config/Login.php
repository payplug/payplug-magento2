<?php

namespace Payplug\Payments\Block\Adminhtml\Config;

use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Data;

class Login extends \Magento\Config\Block\System\Config\Form\Fieldset
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    private $request;

    /**
     * @var string
     */
    private $scope;

    /**
     * @var string
     */
    private $scopeId;

    /**
     * @param \Magento\Backend\Block\Context      $context
     * @param \Magento\Backend\Model\Auth\Session $authSession
     * @param \Magento\Framework\View\Helper\Js   $jsHelper
     * @param Data                                $helper
     * @param array                               $data
     */
    public function __construct(
        \Magento\Backend\Block\Context $context,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\Framework\View\Helper\Js $jsHelper,
        Data $helper,
        array $data = []
    ) {
        $this->helper = $helper;
        $this->request = $context->getRequest();
        parent::__construct($context, $authSession, $jsHelper, $data);
    }

    /**
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $this->setElement($element);
        $header = $this->_getHeaderHtml($element);

        $this->initScopeData();

        $connected = $this->helper->isConnected();
        $isVerified = $this->helper->getAdminConfigValue('verified');
        $isPremium = $this->helper->getAdminConfigValue('premium');

        $connexionFields = ['payment_us_payplug_payments_email'];

        $disconnectionFields = ['payment_us_payplug_payments_account_details'];

        $elements = '';
        foreach ($element->getElements() as $field) {
            if ($connected) {
                if (in_array($field->getId(), $connexionFields)) {
                    continue;
                }
            } else {
                if (in_array($field->getId(), $disconnectionFields)) {
                    continue;
                }
            }

            if ($field instanceof \Magento\Framework\Data\Form\Element\Fieldset) {
                $elements .= '<tr id="row_' . $field->getHtmlId() . '">'
                    . '<td colspan="4">' . $field->toHtml() . '</td></tr>';
            } else {
                $elements .= $field->toHtml();
            }
        }

        $extraElements = '';

        $extraElements .= '<input id="payplug_payments_is_connected" type="hidden" name="payplug_payments_is_connected" 
        value="'.(int)$connected.'" />';

        $extraElements .= '<input id="payplug_payments_is_verified" type="hidden" name="payplug_payments_is_verified" 
        value="'.(int)$isVerified.'" />';

        $extraElements .= '<input id="payplug_payments_is_premium" type="hidden" name="payplug_payments_is_premium" 
        value="'.(int)$isPremium.'" />';

        if ($this->scope == ScopeInterface::SCOPE_WEBSITES) {

            $input = 'payplug_payments_prevent_default';
            if (!$connected) {
                $input = 'payplug_payments_can_override_default';
            }

            $extraElements .= '<input id="' . $input . '" type="hidden" name="' . $input . '" value="1" />';
        }

        $footer = $this->_getFooterHtml($element);

        return $header . $elements . $footer . $extraElements;
    }

    private function initScopeData()
    {
        $this->helper->initScopeData();
        $this->scope = $this->helper->getConfigScope();
        $this->scopeId = $this->helper->getConfigScopeId();
    }
}
