<?php

namespace Payplug\Payments\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Payplug\Payments\Helper\Http\AbstractClient;

class Capture implements ClientInterface
{
    /**
     * @var AbstractClient
     */
    private $client;

    /**
     * @param AbstractClient $client
     */
    public function __construct(AbstractClient $client)
    {
        $this->client = $client;
    }

    /**
     * @inheritdoc
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $data = $transferObject->getBody();
        
        return $this->client->placeRequest($data);
    }
}
