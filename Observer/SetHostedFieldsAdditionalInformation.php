<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;
use Payplug\Payments\Api\Data\OrderPaymentInterface;

class SetHostedFieldsAdditionalInformation extends AbstractDataAssignObserver
{
    /**
     * Add Hosted fields data to payment additional information
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $additionalData = $this->readDataArgument($observer)->getData(PaymentInterface::KEY_ADDITIONAL_DATA);

        $isHostedFieldsPayment = (bool) ($additionalData[OrderPaymentInterface::HF_PAYMENT_KEY] ?? false);
        $hostedFieldsToken = $additionalData[OrderPaymentInterface::HF_TOKEN_KEY] ?? null;
        $hostedFieldsTBrand = $additionalData[OrderPaymentInterface::HF_BRAND_KEY] ?? null;

        $payment = $this->readPaymentModelArgument($observer);
        $payment->setAdditionalInformation(OrderPaymentInterface::HF_PAYMENT_KEY, $isHostedFieldsPayment);
        $payment->setAdditionalInformation(OrderPaymentInterface::HF_TOKEN_KEY, $hostedFieldsToken);
        $payment->setAdditionalInformation(OrderPaymentInterface::HF_BRAND_KEY, $hostedFieldsTBrand);
    }
}
