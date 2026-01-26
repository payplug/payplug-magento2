<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Model\Payment\InstallmentPlan;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Gateway\Config\InstallmentPlan;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Model\Payment\PayplugConfigProvider;

class ConfigProvider extends PayplugConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string
     */
    private $methodCode = InstallmentPlan::METHOD_CODE;

    /**
     * @var MethodInterface
     */
    private $method;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var Config
     */
    private $payplugConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Repository           $assetRepo
     * @param RequestInterface     $request
     * @param PaymentHelper        $paymentHelper
     * @param Config               $payplugConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Repository $assetRepo,
        RequestInterface $request,
        PaymentHelper $paymentHelper,
        Config $payplugConfig
    ) {
        parent::__construct($assetRepo, $request);
        $this->scopeConfig = $scopeConfig;
        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
        $this->payplugConfig = $payplugConfig;
    }

    /**
     * Get InstallmentPlan payment config
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->method->isAvailable() ? [
            'payment' => [
                $this->methodCode => [
                    'logo' => $this->getCardLogo(),
                    'is_embedded' => $this->payplugConfig->isEmbedded(),
                ],
            ],
        ] : [];
    }

    /**
     * Get card logo
     *
     * @return string|null
     */
    public function getCardLogo()
    {
        $localeCode = $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE);

        $filename = 'installment_plan_' . $this->method->getConfigData('count');
        if ($localeCode == 'it_IT') {
            $filename .= '_it';
        } elseif ($localeCode == 'fr_FR') {
            $filename .= '_fr';
        }

        return $this->getViewFileUrl('Payplug_Payments::images/installment_plan/' . $filename . '_@x2.png');
    }
}
