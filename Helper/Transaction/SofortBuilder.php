<?php

namespace Payplug\Payments\Helper\Transaction;

class SofortBuilder extends AbstractBuilder
{
    /**
     * @inheritdoc
     */
    public function buildPaymentData($order, $payment, $quote)
    {
        $paymentData = parent::buildPaymentData($order, $payment, $quote);
        $paymentData['payment_method'] = 'sofort';

        return $paymentData;
    }
}
