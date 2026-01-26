<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

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
    /**
     * @param PayplugDataHelper $payplugDataHelper
     * @param GetAllowedCountriesPerPaymentMethod $getAllowedCountriesPerPaymentMethod
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        private readonly PayplugDataHelper $payplugDataHelper,
        private readonly GetAllowedCountriesPerPaymentMethod $getAllowedCountriesPerPaymentMethod,
        private readonly ConfigHelper $configHelper
    ) {
    }

    /**
     * Hide payment method if shipping address is not in allowed countries
     *
     * @param Observer $observer
     * @return void
     */
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

        if (!in_array($quote->getShippingAddress()->getCountryId(), $allowedCountryIds)) {
            $checkResult->setData('is_available', false);
        }
    }
}
