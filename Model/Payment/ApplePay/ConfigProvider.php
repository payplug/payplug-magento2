<?php

namespace Payplug\Payments\Model\Payment\ApplePay;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Gateway\Config\ApplePay;
use Payplug\Payments\Model\Payment\PayplugConfigProvider;

class ConfigProvider extends PayplugConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string
     */
    private $methodCode = ApplePay::METHOD_CODE;

    /**
     * @var MethodInterface
     */
    private $method;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param Repository           $assetRepo
     * @param RequestInterface     $request
     * @param PaymentHelper        $paymentHelper
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Repository $assetRepo,
        RequestInterface $request,
        PaymentHelper $paymentHelper,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($assetRepo, $request);
        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Get Standard payment config
     *
     * @return array
     */
    public function getConfig()
    {
        $merchandDomain = parse_url($this->scopeConfig->getValue('web/secure/base_url', ScopeInterface::SCOPE_STORE), PHP_URL_HOST);
        $merchandName = $this->scopeConfig->getValue('general/store_information/name', ScopeInterface::SCOPE_STORE);

        return $this->method->isAvailable() ? [
            'payment' => [
                $this->methodCode => [
                    'logo' => $this->getViewFileUrl('Payplug_Payments::images/logos/apple_pay.svg'),
                    'locale' => str_replace(
                        '_',
                        '-',
                        $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE)
                    ),
                    'merchand_name' => $merchandName ?: $merchandDomain,
                    'domain' => $merchandDomain,
                ],
            ],
        ] : [];
    }
}
