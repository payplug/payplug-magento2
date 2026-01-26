<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Model\Payment\OneyWithoutFees;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Gateway\Config\OneyWithoutFees;
use Payplug\Payments\Helper\Oney;
use Payplug\Payments\Model\Payment\PayplugConfigProvider;

class ConfigProvider extends PayplugConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string
     */
    private $methodCode = OneyWithoutFees::METHOD_CODE;

    /**
     * @var MethodInterface
     */
    private $method;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var Oney
     */
    private $oneyHelper;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Repository           $assetRepo
     * @param RequestInterface     $request
     * @param PaymentHelper        $paymentHelper
     * @param Oney                 $oneyHelper
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Repository $assetRepo,
        RequestInterface $request,
        PaymentHelper $paymentHelper,
        Oney $oneyHelper
    ) {
        parent::__construct($assetRepo, $request);
        $this->scopeConfig = $scopeConfig;
        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
        $this->oneyHelper = $oneyHelper;
    }

    /**
     * Get OneyWithoutFees payment config
     *
     * @return array
     */
    public function getConfig()
    {
        $logoPath = 'Payplug_Payments::images/oney_without_fees/3x4x.svg';
        $logoAltPath = 'Payplug_Payments::images/oney_without_fees/3x4x-alt.svg';
        if ($this->isItalianStore()) {
            $logoPath = 'Payplug_Payments::images/oney_without_fees/3x4x-it.svg';
            $logoAltPath = 'Payplug_Payments::images/oney_without_fees/3x4x-alt-it.svg';
        }

        return $this->method->isAvailable() ? [
            'payment' => [
                $this->methodCode => [
                    'logo' => $this->getViewFileUrl($logoPath),
                    'logo_ko' => $this->getViewFileUrl($logoAltPath),
                    'is_italian' => $this->isItalianStore(),
                    'more_info_url' => $this->oneyHelper->isMerchandItalian() ?
                        $this->oneyHelper->getMoreInfoUrlWithoutFees() : null,
                ],
            ],
        ] : [];
    }

    /**
     * Check if current store is in italian
     *
     * @return bool
     */
    private function isItalianStore()
    {
        $localeCode = $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE);

        return $localeCode === 'it_IT';
    }
}
