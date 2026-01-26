<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Helper\Transaction;

use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\Data\CartInterface;

class IdealBuilder extends ApmBuilder
{
    /**
     * @inheritdoc
     */
    public function buildPaymentData($order, InfoInterface $payment, CartInterface $quote): array
    {
        $paymentData = parent::buildPaymentData($order, $payment, $quote);
        $paymentData['payment_method'] = 'ideal';

        return $paymentData;
    }
}
