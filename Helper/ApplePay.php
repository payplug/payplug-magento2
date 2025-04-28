<?php

declare(strict_types=1);

namespace Payplug\Payments\Helper;

use Magento\Framework\App\Helper\Context;
use Payplug\Payments\Gateway\Config\ApplePay as ApplePayConfig;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Payment\Helper\Data;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class ApplePay extends AbstractHelper
{
    /**
     * Constants to display Apple Pay on several pages
     */
    public const ACTIVE_APPLE_PAY_PAGE = 'active';
    public const CART_APPLE_PAY_PAGE = 'show_on_cart';
    public const CHECKOUT_APPLE_PAY_PAGE = 'show_on_checkout';

    private string $applePayMethod;

    public function __construct(
        Context $context,
        private StoreManagerInterface $storeManager,
        private Data $paymentHelper,
    ) {
        parent::__construct($context);
    }

    /**
     * Check if Apple Pay method can display ion specific page
     */
    private function canDisplayApplePayOnPage(string $page): bool
    {
        $applePayPaymentMethod = $this->getApplePayMethod();
        if ($applePayPaymentMethod === '') {
            return false;
        }

        $storeId = $this->storeManager->getStore()->getId();

        return (bool)$this->scopeConfig->getValue(
            'payment/' . $applePayPaymentMethod . '/' . $page,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function canDisplayApplePay(): bool
    {
        return $this->canDisplayApplePayOnPage(ApplePay::ACTIVE_APPLE_PAY_PAGE);
    }

    public function canDisplayApplePayOnCart(): bool
    {
        return $this->canDisplayApplePayOnPage(ApplePay::CART_APPLE_PAY_PAGE);
    }

    public function canDisplayApplePayOncheckout(): bool
    {
        return $this->canDisplayApplePayOnPage(ApplePay::CHECKOUT_APPLE_PAY_PAGE);
    }

    private function getApplePayMethod(): string
    {
        if (!isset($this->applePayMethod)) {
            $applePayMethod = ApplePayConfig::METHOD_CODE;
            if ($this->paymentHelper->getMethodInstance($applePayMethod)->isAvailable()) {
                $this->applePayMethod = $applePayMethod;
            }
        }

        return $this->applePayMethod;
    }
}
