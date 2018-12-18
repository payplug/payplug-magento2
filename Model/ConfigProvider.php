<?php

namespace Payplug\Payments\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Data;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string
     */
    protected $methodCode = PaymentMethod::METHOD_CODE;

    /**
     * @var PaymentMethod
     */
    protected $method;

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
     * @var Data
     */
    private $payplugHelper;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Repository           $assetRepo
     * @param RequestInterface     $request
     * @param PaymentHelper        $paymentHelper
     * @param Data                 $payplugHelper
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Repository $assetRepo,
        RequestInterface $request,
        PaymentHelper $paymentHelper,
        Data $payplugHelper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->assetRepo = $assetRepo;
        $this->request = $request;
        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
        $this->payplugHelper = $payplugHelper;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->method->isAvailable() ? [
            'payment' => [
                $this->methodCode => [
                    'logo' => $this->getCardLogo(),
                    'is_embedded' => $this->payplugHelper->isEmbedded(),
                ],
            ],
        ] : [];
    }

    /**
     * @return string|null
     */
    public function getCardLogo()
    {
        $localeCode = $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE);

        $filename = 'supported_cards';
        if ($localeCode == 'it_IT') {
            $filename = 'supported_cards_it';
        }

        return $this->getViewFileUrl('Payplug_Payments::images/' . $filename . '.png');
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
