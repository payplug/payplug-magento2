<?php

declare(strict_types=1);

namespace Payplug\Payments\Helper\Transaction;

use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\Data\CartInterface;

class MybankBuilder extends ApmBuilder
{
    /**
     * @inheritdoc
     */
    public function buildPaymentData($order, InfoInterface $payment, CartInterface $quote): array
    {
        $paymentData = parent::buildPaymentData($order, $payment, $quote);
        $paymentData['payment_method'] = 'mybank';

        return $paymentData;
    }
}
