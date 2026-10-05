<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Model\Payment;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Payplug\Payments\Helper\Oney as OneyHelper;
use Payplug\Payments\Logger\Logger;
use Throwable;

abstract class AbstractOneyConfigProvider extends PayplugConfigProvider implements ConfigProviderInterface
{
    /**
     * @var MethodInterface
     */
    private MethodInterface $method;

    /**
     * @param Repository $assetRepo
     * @param RequestInterface $request
     * @param PaymentHelper $paymentHelper
     * @param OneyHelper $oneyHelper
     * @param Logger $logger
     * @throws LocalizedException
     */
    public function __construct(
        Repository $assetRepo,
        RequestInterface $request,
        PaymentHelper $paymentHelper,
        protected readonly OneyHelper $oneyHelper,
        private readonly Logger $logger
    ) {
        parent::__construct($assetRepo, $request);

        $this->method = $paymentHelper->getMethodInstance($this->getMethodCode());
    }

    /**
     * Get Oney payment method code
     *
     * @return string
     */
    abstract protected function getMethodCode(): string;

    /**
     * Get logo and unavailable logo file ids, indexed by logo and logo_ko
     *
     * @return array
     */
    abstract protected function getLogos(): array;

    /**
     * Is the Italian rendering of the payment method used
     *
     * @return bool
     */
    protected function isItalian(): bool
    {
        return $this->oneyHelper->isItalianStore();
    }

    /**
     * Get the more info url displayed to Italian merchants
     *
     * @return string
     */
    abstract protected function getMoreInfoUrl(): string;

    /**
     * Get Oney payment config
     *
     * @return array
     */
    public function getConfig(): array
    {
        if (!$this->method->isAvailable()) {
            return [];
        }

        $logos = $this->getLogos();

        return [
            'payment' => [
                $this->getMethodCode() => [
                    'logo' => $this->getViewFileUrl($logos['logo']),
                    'logo_ko' => $this->getViewFileUrl($logos['logo_ko']),
                    'is_italian' => $this->isItalian(),
                    'more_info_url' => $this->oneyHelper->isMerchandItalian() ? $this->getMoreInfoUrl() : null,
                    'widget' => $this->getWidgetConfig(),
                ],
            ],
        ];
    }

    /**
     * Get Oney widget configuration without breaking the checkout when it cannot be built
     *
     * @return array
     */
    private function getWidgetConfig(): array
    {
        try {
            return $this->oneyHelper->getWidgetConfig(null, $this->getMethodCode());
        } catch (Throwable $e) {
            $this->logger->error('Could not build Oney widget configuration', ['exception' => $e]);

            return [];
        }
    }
}
