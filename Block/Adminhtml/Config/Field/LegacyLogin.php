<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Block\Adminhtml\Config\Field;

use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class LegacyLogin extends Field
{
    /**
     * Get button html
     *
     * @param AbstractElement $element
     * @return string
     */
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
