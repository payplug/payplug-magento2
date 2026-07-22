<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;
use Payplug\Payments\Api\Data\OrderPaymentInterface;

class AssignHostedFieldsDataToOrderPayment extends AbstractDataAssignObserver
{
    /**
     * Add Hosted fields data to payment additional information
     *
     * @see AssignPublicCardTokenOnOrderPayment for card token id assignation (common with Payplug Retail)
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $additionalData = $this->readDataArgument($observer)->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        $payment = $this->readPaymentModelArgument($observer);

        $isHostedFieldsPayment = (bool) ($additionalData[OrderPaymentInterface::HF_PAYMENT_KEY] ?? false);
        $hostedFieldsToken = $additionalData[OrderPaymentInterface::HF_TOKEN_KEY] ?? null;
        $hostedFieldsBrand = $additionalData[OrderPaymentInterface::HF_BRAND_KEY] ?? null;
        $hostedFieldsSaveCard = (bool) ($additionalData[OrderPaymentInterface::HF_SAVE_CARD_KEY] ?? false);
        $hostedFieldsCardHolder = $additionalData[OrderPaymentInterface::HF_CARD_HOLDER_KEY] ?? null;

        $hostedFieldsData = [
            OrderPaymentInterface::HF_PAYMENT_KEY => $isHostedFieldsPayment,
            OrderPaymentInterface::HF_TOKEN_KEY => $hostedFieldsToken,
            OrderPaymentInterface::HF_BRAND_KEY => $hostedFieldsBrand,
            OrderPaymentInterface::HF_SAVE_CARD_KEY => $hostedFieldsSaveCard,
            OrderPaymentInterface::HF_CARD_HOLDER_KEY => $hostedFieldsCardHolder,
        ];

        if ($isHostedFieldsPayment === false) {
            foreach (array_keys($hostedFieldsData) as $key) {
                $payment->unsAdditionalInformation($key);
            }

            return;
        }

        foreach ($hostedFieldsData as $key => $value) {
            $payment->setAdditionalInformation($key, $value);
        }
    }
}
