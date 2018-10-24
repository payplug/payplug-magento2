<?php

namespace Payplug\Payments\Plugin\Adminhtml\Widget;

use Payplug\Payments\Model\PaymentMethod;

class SalesOrderViewContext
{
    /**
     * @var PaymentMethod
     */
    protected $paymentMethod;

    /**
     * @param PaymentMethod $paymentMethod
     */
    public function __construct(PaymentMethod $paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;
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
        if ($this->paymentMethod->canUpdatePayment($subject->getOrder())) {
            $subject->addButton('payplug_update_payment', [
                'label'   => __('Update Payment'),
                'onclick' => 'setLocation(\'' . $subject->getUrl('payplug_payments_admin/order/updatePayment') . '\')',
            ]);
        }

        return null;
    }
}
