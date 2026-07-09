<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Helper\Http;

use Payplug\Core\HttpClient;
use Payplug\Exception\ConfigurationException;
use Payplug\Payments\Helper\Config;
use Payplug\Resource\APIResource;

abstract class AbstractClient
{
    /**
     * @param Config $payplugConfig
     */
    public function __construct(
        private readonly Config $payplugConfig
    ) {
    }

    /**
     * Place PayPlug request
     *
     * @param array $data
     * @return array
     * @throws ConfigurationException
     */
    public function placeRequest(array $data): array
    {
        $storeId = $data['store_id'];
        $payplugData = $this->prepareData($data);

        HttpClient::addDefaultUserAgentProduct(
            'PayPlug-Magento2',
            $this->payplugConfig->getModuleVersion(),
            'Magento ' . $this->payplugConfig->getMagentoVersion()
        );

        $isSandbox = $this->payplugConfig->getIsSandbox((int)$storeId);
        $this->payplugConfig->setPayplugApiKey((int)$storeId, $isSandbox);
        $payplugObject = $this->createPayplugObject($payplugData);

        return $this->prepareReturnData($payplugObject, $data);
    }

    /**
     * Remove unnecessary data
     *
     * @param array $data
     * @return array
     */
    protected function prepareData(array $data): array
    {
        unset($data['store_id']);

        return $data;
    }

    /**
     * Create payplug object
     *
     * @param array $payplugData
     * @return APIResource|null
     */
    abstract protected function createPayplugObject(array $payplugData): ?APIResource;

    /**
     * Prepare return data
     *
     * @param APIResource $payplugObject
     * @param array $data
     * @return array
     */
    abstract protected function prepareReturnData(APIResource $payplugObject, array $data): array;
}
