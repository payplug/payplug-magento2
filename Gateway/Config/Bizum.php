<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Gateway\Config;

class Bizum extends PayplugPayment
{
    public const METHOD_CODE = 'payplug_payments_bizum';
}
