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

class OneyDataAssignObserver extends AbstractDataAssignObserver
{
    /**
     * Add Oney data to payment additional information
     *
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer)
    {
        $data = $this->readDataArgument($observer);

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalData)) {
            return;
        }

        if (empty($additionalData['payplug_payments_oney_option'])) {
            return;
        }

        $paymentInfo = $this->readPaymentModelArgument($observer);

        $paymentInfo->setAdditionalInformation(
            'payplug_payments_oney_option',
            $additionalData['payplug_payments_oney_option']
        );
    }
}
