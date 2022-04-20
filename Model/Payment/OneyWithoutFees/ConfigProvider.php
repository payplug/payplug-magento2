<?php

namespace Payplug\Payments\Model\Payment\OneyWithoutFees;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Gateway\Config\OneyWithoutFees;
use Payplug\Payments\Helper\Oney;

class ConfigProvider implements ConfigProviderInterface
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
     * @var Repository
     */
    private $assetRepo;

    /**
     * @var RequestInterface
     */
    private $request;

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
        $this->scopeConfig = $scopeConfig;
        $this->assetRepo = $assetRepo;
        $this->request = $request;
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

    /**
     * Retrieve url of a view file
     *
     * @param string $fileId
     * @param array  $params
     *
     * @return string
     */
    private function getViewFileUrl($fileId, array $params = [])
    {
        try {
            $params = array_merge(['_secure' => $this->request->isSecure()], $params);
            return $this->assetRepo->getUrlWithParams($fileId, $params);
        } catch (LocalizedException $e) {
            return null;
        }
    }
}
