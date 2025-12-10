<?php

declare(strict_types=1);

namespace Payplug\Payments\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
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
    public const CART_APPLE_PAY_PRODUCT = 'show_on_product';
    public const CART_APPLE_PAY_PAGE = 'show_on_cart';
    public const CHECKOUT_APPLE_PAY_PAGE = 'show_on_checkout';

    /**
     * @var string
     */
    private string $applePayMethod;

    /**
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param Data $paymentHelper
     */
    public function __construct(
        Context $context,
        private StoreManagerInterface $storeManager,
        private Data $paymentHelper,
    ) {
        parent::__construct($context);
    }

    /**
     * Check if Apple Pay method can display ion specific page
     *
     * @param string $page
     * @return bool
     * @throws NoSuchEntityException
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

    /**
     * Check if Apple Pay can be displayed on current page
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    public function canDisplayApplePay(): bool
    {
        return $this->canDisplayApplePayOnPage(ApplePay::ACTIVE_APPLE_PAY_PAGE);
    }

    /**
     * Check if Apple Pay can be displayed on product page
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    public function canDisplayApplePayOnProduct(): bool
    {
        return $this->canDisplayApplePayOnPage(ApplePay::CART_APPLE_PAY_PRODUCT);
    }

    /**
     * Check if Apple Pay can be displayed on cart page
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    public function canDisplayApplePayOnCart(): bool
    {
        return $this->canDisplayApplePayOnPage(ApplePay::CART_APPLE_PAY_PAGE);
    }

    /**
     * Check if Apple Pay can be displayed on checkout page
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    public function canDisplayApplePayOncheckout(): bool
    {
        return $this->canDisplayApplePayOnPage(ApplePay::CHECKOUT_APPLE_PAY_PAGE);
    }

    /**
     * Get Apple Pay method code
     *
     * @return string
     * @throws LocalizedException
     */
    private function getApplePayMethod(): string
    {
        if (!isset($this->applePayMethod)) {
            $this->applePayMethod = '';
            $applePayMethod = ApplePayConfig::METHOD_CODE;
            if ($this->paymentHelper->getMethodInstance($applePayMethod)->isAvailable()) {
                $this->applePayMethod = $applePayMethod;
            }
        }

        return $this->applePayMethod;
    }
}
