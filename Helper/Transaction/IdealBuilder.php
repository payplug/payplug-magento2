<?php

declare(strict_types=1);

namespace Payplug\Payments\Helper\Transaction;

use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;

class IdealBuilder extends AbstractBuilder
{
    /**
     * @inheritdoc
     */
    public function buildPaymentData(OrderInterface|OrderAdapterInterface $order, InfoInterface $payment, CartInterface $quote): array
    {
        $paymentData = parent::buildPaymentData($order, $payment, $quote);
        $paymentData['payment_method'] = 'ideal';

        return $paymentData;
    }
}
