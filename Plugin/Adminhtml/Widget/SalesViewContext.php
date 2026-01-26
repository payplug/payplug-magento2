<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Plugin\Adminhtml\Widget;

use Magento\Backend\Block\Widget\Form\Container;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Payplug\Payments\Helper\Data;

class SalesViewContext
{
    /**
     * @param Data $payplugHelper
     * @param FormKey $formKey
     */
    public function __construct(
        private Data $payplugHelper,
        private FormKey $formKey
    ) {
    }

    /**
     * Add PayPlug links to admin order view
     *
     * @param Order $order
     * @param Container $subject
     * @return void
     * @throws LocalizedException
     */
    protected function addPayplugLinks(Order $order, Container $subject): void
    {
        if ($order->getId()) {
            if ($this->payplugHelper->canUpdatePayment($order)) {
                $url = $subject->getUrl('payplug_payments_admin/order/updatePayment', [
                    'order_id' => $order->getId(),
                    'form_key' => $this->formKey->getFormKey() ?: ''
                ]);
                $subject->addButton('payplug_update_payment', [
                    'label'   => __('Update Payment'),
                    'onclick' => 'setLocation(\'' . $url . '\')',
                ]);
            }
            if ($this->payplugHelper->canSendNewPaymentLink($order)) {
                $url = $subject->getUrl('payplug_payments_admin/order/newPaymentLinkForm', [
                    'order_id' => $order->getId(),
                    'form_key' => $this->formKey->getFormKey() ?: ''
                ]);
                $subject->addButton('payplug_send_new_payment_link', [
                    'label'   => __('Send New Payment Link'),
                    'onclick' => 'setLocation(\'' . $url . '\')',
                ]);
            }
        }
    }
}
