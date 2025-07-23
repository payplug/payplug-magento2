<?php

declare(strict_types=1);

namespace Payplug\Payments\Block\Adminhtml\Config;

use Magento\Backend\Block\Widget\Button;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Oauth2Login extends AbstractOauth2
{
    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function _getElementHtml(AbstractElement $element): string
    {
        /** @var Button $buttonBlock */
        $buttonBlock = $this->getForm()->getLayout()->createBlock(Button::class);
        $websiteId = $this->getRequest()->getParam('website');
        $url = $this->getUrl('payplug_payments_admin/config/oauth2Login', ['website' => $websiteId]);

        $data = [
            'label' => $websiteId ? __('Connect to Payplug for this website') : __('Connect to Payplug'),
            'onclick' => "setLocation('$url')",
            'class' => 'action-primary'
        ];

        return $buttonBlock->setData($data)->toHtml();
    }

    public function render(AbstractElement $element): string
    {
        if ($this->isEmailSetForCurrentScope()) {
            return '';
        }

        return parent::render($element);
    }
}
