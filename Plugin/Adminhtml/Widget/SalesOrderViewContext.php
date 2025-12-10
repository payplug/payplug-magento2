<?php

declare(strict_types=1);

namespace Payplug\Payments\Plugin\Adminhtml\Widget;

use Magento\Sales\Block\Adminhtml\Order\View;

class SalesOrderViewContext extends SalesViewContext
{
    /**
     * Add Payplug update payment button on admin order view
     *
     * @param View $subject
     * @return void
     */
    public function beforeSetLayout(View $subject): void
    {
        $this->addPayplugLinks($subject->getOrder(), $subject);
    }
}
