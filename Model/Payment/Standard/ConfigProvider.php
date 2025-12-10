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
use Payplug\Payments\Model\Payment\PayplugConfigProvider;

class ConfigProvider extends PayplugConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string
     */
    private string $methodCode = Standard::METHOD_CODE;

    /**
     * @var MethodInterface
     */
    private MethodInterface $method;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Config $payplugConfig
     * @param Card $payplugCardHelper
     * @param Session $customerSession
     * @param PaymentHelper $paymentHelper
     * @param Repository $assetRepo
     * @param RequestInterface $request
     * @throws LocalizedException
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Config $payplugConfig,
        private readonly Card $payplugCardHelper,
        private readonly Session $customerSession,
        PaymentHelper $paymentHelper,
        Repository $assetRepo,
        RequestInterface $request
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
    public function getCardLogo(): ?string
    {
        $localeCode = $this->getLocaleCode();
        $filename = 'payment-cards';

        if ($localeCode == 'it_IT') {
            $filename .= '-it';
        }

        return $this->getViewFileUrl('Payplug_Payments::images/standard/' . $filename . '.svg');
    }

    /**
     * Get brand logos
     *
     * @return array
     */
    public function getBrandLogos(): array
    {
        $cards = ['mastercard', 'visa', 'other'];
        $logos = [];
        foreach ($cards as $card) {
            $logos[$card] = $this->getViewFileUrl('Payplug_Payments::images/standard/' . $card . '.svg');
        }

        return $logos;
    }

    /**
     * Get locale code
     *
     * @return string
     */
    private function getLocaleCode(): string
    {
        return (string) $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE);
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
     */
    private function isOneClick(): bool
    {
        return $this->payplugConfig->isOneClick() && $this->customerSession->isLoggedIn();
    }

    /**
     * Handle customer cards display depending on checkout module
     */
    private function shouldDisplayCardsInContainer(): bool
    {
        // Fix display of cards with Onestepcheckout module
        // Display cards in payment method container instead of alongside payment title
        return $this->request->getModuleName() === 'onestepcheckout';
    }
}
