<?php

namespace Payplug\Payments\Block;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Gateway\Config\OneyWithoutFees;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Helper\Oney;
use Payplug\Payments\Logger\Logger;

class OneyInfo extends Info
{
    /**
     * Get PayPlug Oney payment details
     *
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
            $status = __('Pending Review');
        }
        $paymentDetails['Status'] = $status;

        $oneyOption = str_replace('oney_', '', $payment->payment_method['type']);
        $paymentMethod = $order->getPayment()->getMethod();
        if (isset(Oney::ALLOWED_OPERATIONS_BY_PAYMENT[$paymentMethod][$oneyOption])) {
            $key = 'Payment in %1';
            if ($paymentMethod === OneyWithoutFees::METHOD_CODE) {
                $key = 'Payment in %1 without fees';
            }
            $paymentDetails['Oney option'] = __($key, Oney::ALLOWED_OPERATIONS_BY_PAYMENT[$paymentMethod][$oneyOption]);
        }

        return $paymentDetails;
    }
}
