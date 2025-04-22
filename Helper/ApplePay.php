<?php

namespace Payplug\Payments\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

class ApplePay extends AbstractHelper
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Payment\Helper\Data
     */
    private $paymentHelper;

    /**
     * @var string
     */
    private $applePayMethod;

    /**
     * @param StoreManagerInterface        $storeManager
     * @param ScopeConfigInterface         $scopeConfig
     * @param \Magento\Payment\Helper\Data $paymentHelper
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Helper\Data $paymentHelper,
    ) {
        $this->storeManager = $storeManager;
        $this->paymentHelper = $paymentHelper;
        $this->scopeConfig = $scopeConfig;
    }
    
    /**
     * Check if Apple Pay method can display ion specific page
     *
     * @return string
     */
    private function canDisplayApplePayOnPage($page): string
    {
        $storeId = $this->storeManager->getStore()->getId();

        $applePayPaymentMethod = $this->getApplePayMethod();

        if ($applePayPaymentMethod === '') {
            return false;
        }

        return $this->scopeConfig->getValue(
            'payment/' . $applePayPaymentMethod . '/' . $page,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check Apple Pay availability
     *
     * @return bool
     */
    public function canDisplayApplePay(): bool
    {
        return $this->canDisplayApplePayOnPage('active');
    }

    /**
     * Check Apple Pay availability on cart page
     *
     * @return bool
     */
    public function canDisplayApplePayOnCart(): bool
    {
        return $this->canDisplayApplePayOnPage('show_on_cart');
    }

    /**
     * Check Apple Pay availability on checkout page
     *
     * @return bool
     */
    public function canDisplayApplePayOncheckout(): bool
    {
        return $this->canDisplayApplePayOnPage('show_on_checkout');
    }

    /**
     * Get available Apple Pay method
     *
     * @return string
     */
    private function getApplePayMethod()
    {
        if ($this->applePayMethod === null) {
            $applePayMethod = \Payplug\Payments\Gateway\Config\ApplePay::METHOD_CODE;

            if ($this->paymentHelper->getMethodInstance($applePayMethod)->isAvailable()) {
                $this->applePayMethod = $applePayMethod;
            }
        }

        return $this->applePayMethod;
    }
}
