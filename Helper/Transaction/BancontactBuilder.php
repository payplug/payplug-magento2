<?php

declare(strict_types=1);

namespace Payplug\Payments\Helper\Transaction;

use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;

class BancontactBuilder extends AbstractBuilder
{
    /**
     * @inheritdoc
     */
    public function buildPaymentData(OrderInterface|OrderAdapterInterface $order, InfoInterface $payment, Quote $quote): array
    {
        $paymentData = parent::buildPaymentData($order, $payment, $quote);
        $paymentData['payment_method'] = 'bancontact';

        return $paymentData;
    }
}
