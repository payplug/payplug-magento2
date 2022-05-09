<?php

namespace Payplug\Payments\Block\Adminhtml\NewPaymentLink;

class SaveButton extends AbstractButton
{
    /**
     * OnDemand send button options
     *
     * @return array
     */
    public function getButtonData()
    {
        return [
            'label'          => __('Send Payment Link'),
            'class'          => 'save primary',
            'on_click' => '',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'save']],
                'form-role' => 'save',
            ],
            'sort_order'     => 30,
        ];
    }
}
