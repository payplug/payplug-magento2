<?php

namespace Payplug\Payments\Helper\Http;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Payplug\Core\HttpClient;
use Payplug\Payments\Helper\Config;
use Payplug\Resource\APIResource;

abstract class AbstractClient extends AbstractHelper
{
    /**
     * @var Config
     */
    private $payplugConfig;

    /**
     * @param Context $context
     * @param Config  $payplugConfig
     */
    public function __construct(Context $context, Config $payplugConfig)
    {
        parent::__construct($context);

        $this->payplugConfig = $payplugConfig;
    }

    /**
     * Place PayPlug request
     *
     * @param array $data
     *
     * @return array
     */
    public function placeRequest($data)
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
     *
     * @param array $data
     *
     * @return array
     */
    protected function prepareData($data)
    {
        unset($data['store_id']);

        return $data;
    }

    /**
     * Create payplug object
     *
     * @param array $payplugData
     *
     * @return APIResource
     */
    abstract protected function createPayplugObject($payplugData);

    /**
     * Prepare return data
     *
     * @param APIResource $payplugObject
     * @param array       $data
     *
     * @return array
     */
    abstract protected function prepareReturnData($payplugObject, $data);
}
