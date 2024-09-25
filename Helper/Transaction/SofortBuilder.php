<?php

declare(strict_types=1);

namespace Payplug\Payments\Helper\Transaction;

use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\Quote;

class SofortBuilder extends AbstractBuilder
{
    /**
     * @inheritdoc
     */
    public function buildPaymentData(OrderAdapterInterface $order, InfoInterface $payment, Quote $quote): array
    {
        $paymentData = parent::buildPaymentData($order, $payment, $quote);
        $paymentData['payment_method'] = 'sofort';

        return $paymentData;
    }
}
