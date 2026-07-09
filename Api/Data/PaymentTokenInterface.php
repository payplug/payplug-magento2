<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Api\Data;

use Magento\Vault\Api\Data\PaymentTokenInterface as BasePaymentTokenInterface;

interface PaymentTokenInterface extends BasePaymentTokenInterface
{
    public const DETAIL_BRAND = 'brand';
    public const MASKED_CC = 'masked_cc';
    public const EXP_DATE = 'exp_date';
    public const HOSTED_FIELD_IDENTIFIER = 'hosted_fields_identifier';
}
