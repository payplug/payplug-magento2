<?php

namespace Payplug\Payments\Plugin\Adminhtml\Widget;

use Magento\Backend\Block\Widget\Form\Container;
use Magento\Sales\Model\Order;
use Payplug\Payments\Helper\Data;

class SalesViewContext
{
    /**
     * @var Data
     */
    private $payplugHelper;

    /**
     * @param Data $payplugHelper
     */
    public function __construct(Data $payplugHelper)
    {
        $this->payplugHelper = $payplugHelper;
    }

    /**
     * Add PayPlug links to admin order view
     *
     * @param Order     $order
     * @param Container $subject
     *
     * @return null
     */
    protected function addPayplugLinks(Order $order, Container $subject)
    {
        if ($order->getId()) {
            if ($this->payplugHelper->canUpdatePayment($order)) {
                $url = $subject->getUrl('payplug_payments_admin/order/updatePayment', [
                    'order_id' => $order->getId()
                ]);
                $subject->addButton('payplug_update_payment', [
                    'label'   => __('Update Payment'),
                    'onclick' => 'setLocation(\'' . $url . '\')',
                ]);
            }
            if ($this->payplugHelper->canSendNewPaymentLink($order)) {
                $url = $subject->getUrl('payplug_payments_admin/order/newPaymentLinkForm', [
                    'order_id' => $order->getId()
                ]);
                $subject->addButton('payplug_send_new_payment_link', [
                    'label'   => __('Send New Payment Link'),
                    'onclick' => 'setLocation(\'' . $url . '\')',
                ]);
            }
        }

        return null;
    }
}
