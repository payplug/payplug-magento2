<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Payplug\Payments\Helper\Http\AbstractClient;

class Capture implements ClientInterface
{
    /**
     * @param AbstractClient $client
     */
    public function __construct(
        private AbstractClient $client
    ) {
    }

    /**
     * @inheritdoc
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $data = $transferObject->getBody();

        return $this->client->placeRequest($data);
    }
}
