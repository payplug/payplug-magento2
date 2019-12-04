<?php

namespace Payplug\Payments\Plugin\Adminhtml\Widget;

class SalesInvoiceViewContext extends SalesViewContext
{
    /**
     * Add Payplug update payment button on admin invoice view
     *
     * @param \Magento\Sales\Block\Adminhtml\Order\Invoice\View $subject
     *
     * @return null
     */
    public function beforeSetLayout(\Magento\Sales\Block\Adminhtml\Order\Invoice\View $subject)
    {
        if ($subject->getInvoice() && $subject->getInvoice()->getId() && $subject->getInvoice()->getOrder()) {
            return $this->addPayplugLinks($subject->getInvoice()->getOrder(), $subject);
        }

        return null;
    }
}
