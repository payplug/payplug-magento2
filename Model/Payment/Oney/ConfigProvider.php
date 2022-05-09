<?php

namespace Payplug\Payments\Model\Payment\Oney;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Payplug\Payments\Gateway\Config\Oney;

class ConfigProvider implements ConfigProviderInterface
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
     * @var \Payplug\Payments\Helper\Oney
     */
    private $oneyHelper;

    /**
     * @param ScopeConfigInterface          $scopeConfig
     * @param Repository                    $assetRepo
     * @param RequestInterface              $request
     * @param PaymentHelper                 $paymentHelper
     * @param \Payplug\Payments\Helper\Oney $oneyHelper
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Repository $assetRepo,
        RequestInterface $request,
        PaymentHelper $paymentHelper,
        \Payplug\Payments\Helper\Oney $oneyHelper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->assetRepo = $assetRepo;
        $this->request = $request;
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
                    'logo' => $this->getViewFileUrl('Payplug_Payments::images/oney/3x4x.svg'),
                    'logo_ko' => $this->getViewFileUrl('Payplug_Payments::images/oney/3x4x-alt.svg'),
                    'is_italian' => false,
                    'more_info_url' => $this->oneyHelper->isMerchandItalian() ?
                        $this->oneyHelper->getMoreInfoUrl() : null,
                ],
            ],
        ] : [];
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
