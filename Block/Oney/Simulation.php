<?php

namespace Payplug\Payments\Block\Oney;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template;
use Payplug\Payments\Helper\Oney;
use Payplug\Payments\Model\OneySimulation\Result;

class Simulation extends Template
{
    /**
     * @var float
     */
    private $amount;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var Oney
     */
    private $oneyHelper;

    /**
     * @param Template\Context $context
     * @param CheckoutSession  $checkoutSession
     * @param CustomerSession  $customerSession
     * @param Oney             $oneyHelper
     * @param array            $data
     */
    public function __construct(
        Template\Context $context,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        Oney $oneyHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->oneyHelper = $oneyHelper;
    }

    /**
     * @inheritdoc
     */
    public function _toHtml(): string
    {
        if ($this->oneyHelper->canDisplayOney()) {
            return parent::_toHtml();
        }

        // Don't render template in case oney isn't available
        return '';
    }

    /**
     * @param float $amount
     *
     * @return $this
     */
    public function setAmount(float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * @return float
     */
    public function getAmount(): float
    {
        if ($this->amount !== null) {
            return $this->amount;
        }

        return $this->checkoutSession->getQuote()->getGrandTotal();
    }

    /**
     * @return Result
     */
    public function getOneySimulation(): Result
    {
        $shippingMethod = null;
        if (!$this->checkoutSession->getQuote()->isVirtual()) {
            $shippingMethod = $this->checkoutSession->getQuote()->getShippingAddress()->getShippingMethod();
        }
        return $this->oneyHelper->getOneySimulation($this->getAmount(), $this->getCountry(), $shippingMethod);
    }

    /**
     * @return array|bool
     */
    public function getOneyAmounts()
    {
        return $this->oneyHelper->getOneyAmounts();
    }

    /**
     * @return string
     */
    private function getCountry(): string
    {
        $quote = $this->checkoutSession->getQuote();
        $billingAddress = $quote->getBillingAddress();
        if (!empty($billingAddress->getCountryId())) {
            return $billingAddress->getCountryId();
        }

        if (!$quote->isVirtual()) {
            $shippingAddress = $quote->getShippingAddress();
            if (!empty($shippingAddress->getCountryId())) {
                return $shippingAddress->getCountryId();
            }
        }

        if ($this->customerSession->isLoggedIn()) {
            $defaultBilling = $this->customerSession->getCustomer()->getDefaultBillingAddress();
            if ($defaultBilling !== false && !empty($defaultBilling->getCountryId())) {
                return $defaultBilling->getCountryId();
            }
            if (!$quote->isVirtual()) {
                $defaultShipping = $this->customerSession->getCustomer()->getDefaultShippingAddress();
                if ($defaultShipping !== false && !empty($defaultShipping->getCountryId())) {
                    return $defaultShipping->getCountryId();
                }
            }
        }

        return 'FR';
    }
}
