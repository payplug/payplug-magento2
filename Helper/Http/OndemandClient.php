<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Helper\Http;

use Payplug\Resource\APIResource;

class OndemandClient extends StandardClient
{
    /**
     * @inheritdoc
     */
    protected function prepareReturnData(APIResource $payplugObject, array $data): array
    {
        return array_merge(parent::prepareReturnData($payplugObject, $data), ['ondemandData' => $data['extra']]);
    }

    /**
     * @inheritdoc
     */
    protected function prepareData(array $data): array
    {
        $data = parent::prepareData($data);
        unset($data['extra']);

        return $data;
    }
}
