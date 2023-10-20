<?php

namespace Payplug\Payments\Helper\Transaction;

class SatispayBuilder extends AbstractBuilder
{
    /**
     * @inheritdoc
     */
    public function buildPaymentData($order, $payment, $quote)
    {
        $paymentData = parent::buildPaymentData($order, $payment, $quote);
        $paymentData['payment_method'] = 'satispay';

        return $paymentData;
    }
}
