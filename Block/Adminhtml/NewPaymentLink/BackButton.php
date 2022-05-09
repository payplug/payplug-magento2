<?php

namespace Payplug\Payments\Block\Adminhtml\NewPaymentLink;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Registry;

class BackButton extends AbstractButton
{
    /**
     * OnDemand back button options
     *
     * @return array
     */
    public function getButtonData()
    {
        return [
            'label'      => __('Back'),
            'on_click'   => sprintf("location.href = '%s';", $this->getBackUrl()),
            'class'      => 'back',
            'sort_order' => 10,
        ];
    }

    /**
     * Build order view url
     *
     * @return string
     */
    public function getBackUrl()
    {
        return $this->context->getUrlBuilder()->getUrl('sales/order/view', [
            'order_id' => $this->registry->registry('current_order')->getId()
        ]);
    }
}
