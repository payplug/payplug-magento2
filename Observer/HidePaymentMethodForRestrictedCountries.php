<?php

namespace Payplug\Payments\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Model\Method\Adapter as MethodAdapter;
use Magento\Quote\Api\Data\CartInterface;
use Payplug\Payments\Gateway\Config\Bancontact as BancontactConfig;
use Payplug\Payments\Helper\Config as PayplugConfigHelper;
use Payplug\Payments\Helper\Data as PayplugDataHelper;

class HidePaymentMethodForRestrictedCountries implements ObserverInterface
{
    public function __construct(
        private readonly PayplugConfigHelper $payplugConfigHelper,
        private readonly PayplugDataHelper $payplugDataHelper
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

        $selectedCarrierMethod = str_replace('payplug_payments_', '', $paymentMethod);

        if ($selectedCarrierMethod === 'bancontact') {
            /**
             * @todo waiting for feedbacks
             */
            $restrictedCountryIds = ['BE'];
        } else {
            $restrictedCountryIds = json_decode(
                $this->payplugConfigHelper->getConfigValue($selectedCarrierMethod . '_countries'),
                true
            );
        }

        $selectedCountryId = $quote->getShippingAddress()->getCountryId()
            ?: $quote->getBillingAddress()->getCountryId();

        if (!is_array($restrictedCountryIds) || !in_array($selectedCountryId, $restrictedCountryIds)) {
            $checkResult->setData('is_available', false);
        }
    }
}
