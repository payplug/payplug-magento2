<?php

namespace Payplug\Payments\Gateway\Http\Client\InstallmentPlan;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Payplug\Core\HttpClient;
use Payplug\Payments\Helper\Config;

class Capture implements ClientInterface
{
    /**
     * @var Config
     */
    private $payplugConfig;

    public function __construct(Config $payplugConfig)
    {
        $this->payplugConfig = $payplugConfig;
    }

    public function placeRequest(TransferInterface $transferObject)
    {
        $data = $transferObject->getBody();

        $storeId = $data['store_id'];
        unset($data['store_id']);

        HttpClient::addDefaultUserAgentProduct(
            'PayPlug-Magento2',
            $this->payplugConfig->getModuleVersion(),
            'Magento ' . $this->payplugConfig->getMagentoVersion()
        );

        $isSandbox = $this->payplugConfig->getIsSandbox($storeId);

        $this->payplugConfig->setPayplugApiKey($storeId, $isSandbox);
        $installmentPlan = \Payplug\InstallmentPlan::create($data);

        return ['installment_plan' => $installmentPlan];
    }
}
