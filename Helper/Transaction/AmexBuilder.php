<?php

namespace Payplug\Payments\Helper\Transaction;

class AmexBuilder extends AbstractBuilder
{
    /**
     * @inheritdoc
     */
    public function buildPaymentData($order, $payment, $quote)
    {
        $paymentData = parent::buildPaymentData($order, $payment, $quote);
        $paymentData['payment_method'] = 'american_express';

        return $paymentData;
    }
}
