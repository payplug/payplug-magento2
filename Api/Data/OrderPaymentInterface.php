<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Api\Data;

use Magento\Sales\Api\Data\OrderPaymentInterface as BaseOrderPaymentInterface;

interface OrderPaymentInterface extends BaseOrderPaymentInterface
{
    public const HF_PAYMENT_KEY = 'payplug_hosted_fields_payment';
    public const HF_TOKEN_KEY = 'payplug_hosted_fields_token';
    public const HF_BRAND_KEY = 'payplug_hosted_fields_brand';
}
