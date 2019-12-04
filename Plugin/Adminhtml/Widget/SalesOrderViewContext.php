<?php

namespace Payplug\Payments\Plugin\Adminhtml\Widget;

class SalesOrderViewContext extends SalesViewContext
{
    /**
     * Add Payplug update payment button on admin order view
     *
     * @param \Magento\Sales\Block\Adminhtml\Order\View $subject
     *
     * @return null
     */
    public function beforeSetLayout(\Magento\Sales\Block\Adminhtml\Order\View $subject)
    {
        return $this->addPayplugLinks($subject->getOrder(), $subject);
    }
}
