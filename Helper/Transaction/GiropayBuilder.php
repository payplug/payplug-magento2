<?php

namespace Payplug\Payments\Helper\Transaction;

class GiropayBuilder extends AbstractBuilder
{
    /**
     * @inheritdoc
     */
    public function buildPaymentData($order, $payment, $quote)
    {
        $paymentData = parent::buildPaymentData($order, $payment, $quote);
        $paymentData['payment_method'] = 'giropay';

        return $paymentData;
    }
}
