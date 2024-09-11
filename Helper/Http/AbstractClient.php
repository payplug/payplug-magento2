<?php

declare(strict_types=1);

namespace Payplug\Payments\Helper\Http;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Payplug\Core\HttpClient;
use Payplug\Payments\Helper\Config;
use Payplug\Resource\APIResource;

abstract class AbstractClient extends AbstractHelper
{
    public function __construct(
        Context $context,
        private Config $payplugConfig
    ) {
        parent::__construct($context);
    }

    /**
     * Place PayPlug request
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

        $isSandbox = $this->payplugConfig->getIsSandbox($storeId);
        $this->payplugConfig->setPayplugApiKey($storeId, $isSandbox);
        $payplugObject = $this->createPayplugObject($payplugData);

        return $this->prepareReturnData($payplugObject, $data);
    }

    /**
     * Remove unnecessary data
     */
    protected function prepareData(array $data): array
    {
        unset($data['store_id']);

        return $data;
    }

    /**
     * Create payplug object
     */
    abstract protected function createPayplugObject(array $payplugData): ?APIResource;

    /**
     * Prepare return data
     */
    abstract protected function prepareReturnData(APIResource $payplugObject, array $data): array;
}
