<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Helper\Http;

use Payplug\Payment;
use Payplug\Resource\APIResource;

class StandardClient extends AbstractClient
{
    /**
     * @inheritdoc
     */
    protected function prepareReturnData(APIResource $payplugObject, array $data): array
    {
        return ['payment' => $payplugObject];
    }

    /**
     * @inheritdoc
     */
    protected function createPayplugObject(array $payplugData): ?APIResource
    {
        return Payment::create($payplugData);
    }
}
