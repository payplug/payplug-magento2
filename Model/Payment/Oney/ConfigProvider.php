<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Model\Payment\Oney;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Payplug\Payments\Gateway\Config\Oney;
use Payplug\Payments\Model\Payment\PayplugConfigProvider;

class ConfigProvider extends PayplugConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string
     */
    private $methodCode = Oney::METHOD_CODE;

    /**
     * @var MethodInterface
     */
    private $method;

    /**
     * @var \Payplug\Payments\Helper\Oney
     */
    private $oneyHelper;

    /**
     * @param Repository                    $assetRepo
     * @param RequestInterface              $request
     * @param PaymentHelper                 $paymentHelper
     * @param \Payplug\Payments\Helper\Oney $oneyHelper
     */
    public function __construct(
        Repository $assetRepo,
        RequestInterface $request,
        PaymentHelper $paymentHelper,
        \Payplug\Payments\Helper\Oney $oneyHelper
    ) {
        parent::__construct($assetRepo, $request);
        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
        $this->oneyHelper = $oneyHelper;
    }

    /**
     * Get Oney payment config
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->method->isAvailable() ? [
            'payment' => [
                $this->methodCode => [
                    'logo' => $this->getViewFileUrl('Payplug_Payments::images/logos/oney_3x_4x.svg'),
                    'logo_ko' => $this->getViewFileUrl('Payplug_Payments::images/logos/oney_3x_4x_alt.svg'),
                    'is_italian' => false,
                    'more_info_url' => $this->oneyHelper->isMerchandItalian() ?
                        $this->oneyHelper->getMoreInfoUrl() : null,
                ],
            ],
        ] : [];
    }
}
