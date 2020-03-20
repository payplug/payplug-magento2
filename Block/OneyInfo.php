<?php

namespace Payplug\Payments\Block;

use Magento\Framework\Exception\NoSuchEntityException;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Helper\Oney;
use Payplug\Payments\Logger\Logger;

class OneyInfo extends Info
{
    /**
     * @param \Payplug\Resource\Payment $payment
     * @param Order                     $order
     *
     * @return array
     */
    protected function buildPaymentDetails($payment, $order)
    {
        $paymentDetails = parent::buildPaymentDetails($payment, $order);

        $status = __('Not Paid');
        if ($payment->is_refunded) {
            $status = __('Refunded');
        } elseif ($payment->amount_refunded > 0) {
            $status = __('Partially Refunded');
        } elseif ($payment->is_paid) {
            $status = __('Paid');
        } elseif ($payment->authorization->authorized_at) {
            $status = __('Authorized');
        } elseif ($payment->payment_method['is_pending']) {
            $status = __('Pending review');
        }
        $paymentDetails['Status'] = $status;

        $oneyOption = str_replace('oney_', '', $payment->payment_method['type']);
        if (isset(Oney::ALLOWED_OPERATIONS[$oneyOption])) {
            $paymentDetails['Oney Option'] = __('Payment in %1', Oney::ALLOWED_OPERATIONS[$oneyOption]);
        }

        return $paymentDetails;
    }
}
