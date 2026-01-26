<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Model\Payment\ApplePay;

use Laminas\Uri\Http as UriHelper;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Gateway\Config\ApplePay;
use Payplug\Payments\Helper\ApplePay as ApplePayHelper;
use Payplug\Payments\Model\Payment\PayplugConfigProvider;

class ConfigProvider extends PayplugConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string
     */
    private string $methodCode = ApplePay::METHOD_CODE;
    /**
     * @var MethodInterface
     */
    private MethodInterface $method;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ApplePayHelper $applePayHelper
     * @param UriHelper $uriHelper
     * @param Repository $assetRepo
     * @param RequestInterface $request
     * @param PaymentHelper $paymentHelper
     * @throws LocalizedException
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ApplePayHelper $applePayHelper,
        private readonly UriHelper $uriHelper,
        Repository $assetRepo,
        RequestInterface $request,
        PaymentHelper $paymentHelper
    ) {
        parent::__construct($assetRepo, $request);

        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
    }

    /**
     * Get Standard payment config
     */
    public function getConfig(): array
    {
        $baseUrl = $this->scopeConfig->getValue('web/secure/base_url', ScopeInterface::SCOPE_STORE);
        $merchandDomain = $this->uriHelper->parse($baseUrl)->getHost();
        $merchandName = $this->scopeConfig->getValue('general/store_information/name', ScopeInterface::SCOPE_STORE);

        $allowed = $this->method->isAvailable() && $this->applePayHelper->canDisplayApplePayOncheckout();

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
                    'enabled_on_checkout' => $allowed
                ],
            ],
        ] : [];
    }
}
