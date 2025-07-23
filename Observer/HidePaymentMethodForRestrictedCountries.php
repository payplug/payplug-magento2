<?php

namespace Payplug\Payments\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Model\Method\Adapter as MethodAdapter;
use Magento\Quote\Api\Data\CartInterface;
use Payplug\Payments\Gateway\Config\Bancontact as BancontactConfig;
use Payplug\Payments\Helper\Config as ConfigHelper;
use Payplug\Payments\Helper\Data as PayplugDataHelper;
use Payplug\Payments\Service\GetAllowedCountriesPerPaymentMethod;

class HidePaymentMethodForRestrictedCountries implements ObserverInterface
{
    public function __construct(
        private readonly PayplugDataHelper $payplugDataHelper,
        private readonly GetAllowedCountriesPerPaymentMethod $getAllowedCountriesPerPaymentMethod,
        private readonly ConfigHelper $configHelper
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var CartInterface $quote */
        $quote = $observer->getEvent()->getData('quote');

        if ($quote === null) {
            return;
        }

        /** @var MethodAdapter $methodAdapter */
        $methodAdapter = $observer->getEvent()->getData('method_instance');
        /** @var DataObject $checkResult */
        $checkResult = $observer->getEvent()->getData('result');
        $paymentMethod = $methodAdapter->getCode();

        if ($this->payplugDataHelper->isCodePayplugPaymentPpro($paymentMethod) === false
            && $paymentMethod !== BancontactConfig::METHOD_CODE
        ) {
            return;
        }

        if (!$this->configHelper->isShippingApmFilteringMode()) {
            return;
        }

        $allowedCountryIds = $this->getAllowedCountriesPerPaymentMethod->execute($paymentMethod);
        $selectedCountryIds = [
            $quote->getShippingAddress()->getCountryId(),
            $quote->getBillingAddress()->getCountryId(),
        ];

        if (!array_intersect($allowedCountryIds, $selectedCountryIds)) {
            $checkResult->setData('is_available', false);
        }
    }
}
