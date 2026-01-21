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

class BizumBuilder extends ApmBuilder
{
    protected const NEED_PHONE_NUMBER_REGION_CHECK = true;
    protected const IS_MOBILE_PHONE_NUMBER_REQUIRED = true;
    protected const MOBILE_PHONE_NUMBER_WHITELIST = [
        '+34700000000'
    ];

    /**
     * @inheritdoc
     */
    public function buildPaymentData($order, InfoInterface $payment, CartInterface $quote): array
    {
        $paymentData = parent::buildPaymentData($order, $payment, $quote);
        $paymentData['payment_method'] = 'bizum';

        return $paymentData;
    }
}
