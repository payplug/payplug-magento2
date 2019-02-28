<?php

namespace Payplug\Payments\Model\Payment\Standard;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Gateway\Config\Standard;
use Payplug\Payments\Helper\Card;
use Payplug\Payments\Helper\Config;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string
     */
    private $methodCode = Standard::METHOD_CODE;

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
     * @var Config
     */
    private $payplugConfig;

    /**
     * @var Card
     */
    private $payplugCardHelper;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Repository           $assetRepo
     * @param RequestInterface     $request
     * @param PaymentHelper        $paymentHelper
     * @param Config               $payplugConfig
     * @param Card                 $payplugCardHelper
     * @param Session              $customerSession
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Repository $assetRepo,
        RequestInterface $request,
        PaymentHelper $paymentHelper,
        Config $payplugConfig,
        Card $payplugCardHelper,
        Session $customerSession
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->assetRepo = $assetRepo;
        $this->request = $request;
        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
        $this->payplugConfig = $payplugConfig;
        $this->payplugCardHelper = $payplugCardHelper;
        $this->customerSession = $customerSession;
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
                    'is_one_click' => $this->method->getConfigData('one_click'),
                    'brand_logos' => $this->getBrandLogos(),
                    'selected_card_id' => $this->getSelectedCardId(),
                    'should_refresh_cards' => $this->shouldRefreshCards(),
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
     * @return array
     */
    public function getBrandLogos()
    {
        $cards = ['mastercard', 'visa', 'other'];
        $logos = [];
        foreach ($cards as $card) {
            $logos[$card] = $this->getViewFileUrl('Payplug_Payments::images/' . $card . '.png');
        }

        return $logos;
    }

    /**
     * @return int|string
     */
    public function getSelectedCardId()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return '';
        }

        $lastCardId = $this->payplugCardHelper->getLastCardIdByCustomer($this->customerSession->getCustomer()->getId());

        if ($lastCardId != 0) {
            return $lastCardId;
        }

        return '';
    }

    /**
     * @return bool
     */
    public function shouldRefreshCards()
    {
        // Issue in Magento 2.1 with private content not refreshed properly by Magento
        return strpos($this->payplugConfig->getMagentoVersion(), '2.1.') === 0;
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
