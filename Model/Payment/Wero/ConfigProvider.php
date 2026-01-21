<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Model\Payment\Wero;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Payplug\Payments\Gateway\Config\Wero;
use Payplug\Payments\Model\Payment\PayplugConfigProvider;

class ConfigProvider extends PayplugConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string
     */
    private string $methodCode = Wero::METHOD_CODE;

    /**
     * @var MethodInterface
     */
    private MethodInterface $method;

    /**
     * @param Repository $assetRepo
     * @param RequestInterface $request
     * @param PaymentHelper $paymentHelper
     * @throws LocalizedException
     */
    public function __construct(
        Repository $assetRepo,
        RequestInterface $request,
        PaymentHelper $paymentHelper
    ) {
        parent::__construct($assetRepo, $request);
        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
    }

    /**
     * Get Standard payment config
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->method->isAvailable() ? [
            'payment' => [
                $this->methodCode => [
                    'logo' => $this->getViewFileUrl('Payplug_Payments::images/logos/wero.svg'),
                ],
            ],
        ] : [];
    }
}
