<?php

namespace Payplug\Payments\Block\Adminhtml\Config;

use Magento\Backend\Block\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Config\Block\System\Config\Form\Fieldset;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\Fieldset as FieldsetElement;
use Magento\Framework\View\Helper\Js as JsHelper;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config;

class Login extends Fieldset
{
    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @param Config $helper
     * @param Context $context
     * @param Session $authSession
     * @param JsHelper $jsHelper
     * @param array $data
     */
    public function __construct(
        private readonly Config $helper,
        Context $context,
        Session $authSession,
        JsHelper $jsHelper,
        array $data = []
    ) {
        parent::__construct($context, $authSession, $jsHelper, $data);

        $this->request = $context->getRequest();
    }

    /**
     * Render element
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $this->setElement($element);
        $header = $this->_getHeaderHtml($element);

        $this->helper->initScopeData();

        $legacyConnected = $this->helper->isLegacyConnected(
            ScopeInterface::SCOPE_WEBSITE,
            $this->request->getParam('website')
        );
        $oauthConnected = $this->helper->isOauthConnected(
            ScopeInterface::SCOPE_WEBSITE,
            $this->request->getParam('website')
        );

        $isVerified = $this->helper->getConfigValue(
            'verified',
            ScopeInterface::SCOPE_WEBSITE,
            $this->request->getParam('website')
        );

        $connexionFields = ['payplug_payments_auth_email', 'payplug_payments_auth_connect'];
        $connexionFieldsWithPwd = [
            'payplug_payments_auth_email',
            'payplug_payments_auth_pwd',
            'payplug_payments_auth_connect'
        ];

        $disconnectionFields = ['payplug_payments_auth_account_details'];

        $elements = '';
        foreach ($element->getElements() as $field) {
            if ($oauthConnected) {
                if (in_array($field->getId(), $connexionFieldsWithPwd)) {
                    continue;
                }
            }

            if ($legacyConnected) {
                if (in_array($field->getId(), $connexionFields)) {
                    continue;
                }
            } else {
                if (in_array($field->getId(), $disconnectionFields)) {
                    continue;
                }
            }

            if ($field instanceof FieldsetElement) {
                $elements .= '<tr id="row_' . $field->getHtmlId() . '">'
                    . '<td colspan="4">' . $field->toHtml() . '</td></tr>';
            } else {
                $elements .= $field->toHtml();
            }
        }

        $extraElements = '';

        $extraElements .= '<input id="payplug_payments_is_connected" type="hidden" name="payplug_payments_is_connected"
        value="' . (int)$legacyConnected . '" />';

        $extraElements .= '<input id="payplug_payments_is_verified" type="hidden" name="payplug_payments_is_verified"
        value="' . (int)$isVerified . '" />';

        if ($this->helper->getConfigScope() == ScopeInterface::SCOPE_WEBSITES) {
            $input = 'payplug_payments_prevent_default';
            if (!$legacyConnected) {
                $input = 'payplug_payments_can_override_default';
            }

            $extraElements .= '<input id="' . $input . '" type="hidden" name="' . $input . '" value="1" />';
        }

        $footer = $this->_getFooterHtml($element);

        return $header . $elements . $footer . $extraElements;
    }
}
