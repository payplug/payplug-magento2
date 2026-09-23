<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Model\Payment\OneyWithoutFees;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
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
     * @var Oney
     */
    private $oneyHelper;

    /**
     * @param Repository           $assetRepo
     * @param RequestInterface     $request
     * @param PaymentHelper        $paymentHelper
     * @param Oney                 $oneyHelper
     */
    public function __construct(
        Repository $assetRepo,
        RequestInterface $request,
        PaymentHelper $paymentHelper,
        Oney $oneyHelper
    ) {
        parent::__construct($assetRepo, $request);
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
        $isItalianStore = $this->oneyHelper->isItalianStore();
        $logoPath = 'Payplug_Payments::images/oney_without_fees/3x4x.svg';
        $logoAltPath = 'Payplug_Payments::images/oney_without_fees/3x4x-alt.svg';
        if ($isItalianStore) {
            $logoPath = 'Payplug_Payments::images/oney_without_fees/3x4x-it.svg';
            $logoAltPath = 'Payplug_Payments::images/oney_without_fees/3x4x-alt-it.svg';
        }

        return $this->method->isAvailable() ? [
            'payment' => [
                $this->methodCode => [
                    'logo' => $this->getViewFileUrl($logoPath),
                    'logo_ko' => $this->getViewFileUrl($logoAltPath),
                    'is_italian' => $isItalianStore,
                    'more_info_url' => $this->oneyHelper->isMerchandItalian() ?
                        $this->oneyHelper->getMoreInfoUrlWithoutFees() : null,
                    'widget' => $this->oneyHelper->getWidgetConfig(null, $this->methodCode),
                ],
            ],
        ] : [];
    }
}
