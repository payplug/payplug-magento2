<?php

declare(strict_types=1);

namespace Payplug\Payments\Block\Adminhtml\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config;

class LegacyLogin extends Field
{
    protected function _getElementHtml(AbstractElement $element): string
    {
        /** @var Button $buttonBlock */
        $buttonBlock = $this->getForm()->getLayout()->createBlock(Button::class);
        $websiteId = $this->getRequest()->getParam('website');

        $data = [
            'label' => $websiteId ? __('Connect to Payplug for this website') : __('Connect to Payplug'),
            'onclick' => 'document.querySelector("#save").click();',
            'class' => 'action-primary'
        ];

        return $buttonBlock->setData($data)->toHtml();
    }
}
