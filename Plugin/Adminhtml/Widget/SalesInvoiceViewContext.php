<?php

declare(strict_types=1);

namespace Payplug\Payments\Plugin\Adminhtml\Widget;

use Magento\Sales\Block\Adminhtml\Order\Invoice\View;

class SalesInvoiceViewContext extends SalesViewContext
{
    /**
     * Add Payplug update payment button on admin invoice view
     *
     * @param View $subject
     * @return void
     */
    public function beforeSetLayout(View $subject): void
    {
        if ($subject->getInvoice() && $subject->getInvoice()->getId() && $subject->getInvoice()->getOrder()) {
            $this->addPayplugLinks($subject->getInvoice()->getOrder(), $subject);
        }
    }
}
