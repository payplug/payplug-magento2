<?php

namespace Payplug\Payments\Plugin\Adminhtml\Widget;

use Payplug\Payments\Helper\Data;

class SalesOrderViewContext
{
    /**
     * @var Data
     */
    protected $payplugHelper;

    /**
     * @param Data $payplugHelper
     */
    public function __construct(Data $payplugHelper)
    {
        $this->payplugHelper = $payplugHelper;
    }

    /**
     * Add Payplug update payment button on admin order view
     *
     * @param \Magento\Sales\Block\Adminhtml\Order\View $subject
     *
     * @return null
     */
    public function beforeSetLayout(\Magento\Sales\Block\Adminhtml\Order\View $subject)
    {
        if ($this->payplugHelper->canUpdatePayment($subject->getOrder())) {
            $subject->addButton('payplug_update_payment', [
                'label'   => __('Update Payment'),
                'onclick' => 'setLocation(\'' . $subject->getUrl('payplug_payments_admin/order/updatePayment') . '\')',
            ]);
        }

        return null;
    }
}
