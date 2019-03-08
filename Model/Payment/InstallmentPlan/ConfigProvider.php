<?php

namespace Payplug\Payments\Model\Payment\InstallmentPlan;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Gateway\Config\InstallmentPlan;
use Payplug\Payments\Helper\Config;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string
     */
    private $methodCode = InstallmentPlan::METHOD_CODE;

    /**
     * @var Standard
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
        $this->scopeConfig = $scopeConfig;
        $this->assetRepo = $assetRepo;
        $this->request = $request;
        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
        $this->payplugConfig = $payplugConfig;
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
                    'is_embedded' => $this->payplugConfig->isEmbedded(),
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

        $filename = 'installment_plan_' . $this->method->getConfigData('count');
        if ($localeCode == 'it_IT') {
            $filename .= '_it';
        } elseif ($localeCode == 'fr_FR') {
            $filename .= '_fr';
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
