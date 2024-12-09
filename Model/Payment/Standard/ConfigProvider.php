<?php

namespace Payplug\Payments\Model\Payment\Standard;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Gateway\Config\Standard;
use Payplug\Payments\Helper\Card;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Model\Payment\PayplugConfigProvider;

class ConfigProvider extends PayplugConfigProvider implements ConfigProviderInterface
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
        parent::__construct($assetRepo, $request);
        $this->scopeConfig = $scopeConfig;
        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
        $this->payplugConfig = $payplugConfig;
        $this->payplugCardHelper = $payplugCardHelper;
        $this->customerSession = $customerSession;
    }

    /**
     * Get Standard payment config
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->method->isAvailable() ? [
            'payment' => [
                $this->methodCode => [
                    'logo' => $this->getCardLogo(),
                    'is_embedded' => $this->payplugConfig->isEmbedded(),
                    'is_integrated' => $this->payplugConfig->isIntegrated(),
                    'is_one_click' => $this->isOneClick(),
                    'brand_logos' => $this->getBrandLogos(),
                    'selected_card_id' => $this->getSelectedCardId(),
                    'display_cards_in_container' => $this->shouldDisplayCardsInContainer(),
                    'is_sandbox' => $this->payplugConfig->getIsSandbox(),
                    'locale_code' => $this->getLocaleCode(),
                ],
            ],
        ] : [];
    }

    /**
     * Get card logo
     *
     * @return string|null
     */
    public function getCardLogo()
    {
        $localeCode = $this->getLocaleCode();
        $filename = 'payment-cards';

        if ($localeCode == 'it_IT') {
            $filename .= '-it';
        }

        return $this->getViewFileUrl('Payplug_Payments::images/icons/' . $filename . '.svg');
    }

    /**
     * Get brand logos
     *
     * @return array
     */
    public function getBrandLogos()
    {
        $cards = ['mastercard', 'visa', 'other'];
        $logos = [];
        foreach ($cards as $card) {
            $logos[$card] = $this->getViewFileUrl('Payplug_Payments::images/' . $card . '.svg');
        }

        return $logos;
    }

    /**
     * Get store locale
     *
     * @return string
     */
    private function getLocaleCode()
    {
        return $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE);;
    }

    /**
     * Get selected card id
     *
     * @return int|string
     */
    public function getSelectedCardId()
    {
        if (!$this->isOneClick()) {
            return '';
        }

        $customerId = $this->customerSession->getCustomer()->getId();
        $lastCardId = $this->payplugCardHelper->getLastCardIdByCustomer($customerId);

        if ($lastCardId === 0) {
            return '';
        }
        $customerCardsForCurrentContext = $this->payplugCardHelper->getCardsByCustomer($customerId);
        foreach ($customerCardsForCurrentContext as $card) {
            if ($card->getCustomerCardId() === $lastCardId) {
                return $lastCardId;
            }
        }

        return '';
    }

    /**
     * Check if customer is logged in to enable one click
     *
     * @return bool
     */
    private function isOneClick()
    {
        return $this->payplugConfig->isOneClick() && $this->customerSession->isLoggedIn();
    }

    /**
     * Handle customer cards display depending on checkout module
     *
     * @return bool
     */
    private function shouldDisplayCardsInContainer()
    {
        // Fix display of cards with Onestepcheckout module
        // Display cards in payment method container instead of alongside payment title
        return $this->request->getModuleName() === 'onestepcheckout';
    }
}
