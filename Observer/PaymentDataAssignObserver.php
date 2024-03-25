<?php

namespace Payplug\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class PaymentDataAssignObserver extends AbstractDataAssignObserver
{
    /**
     * Add Card data to payment additional information
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

        $cardId = null;
        if (isset($additionalData['payplug_payments_customer_card_id'])) {
            $cardId = $additionalData['payplug_payments_customer_card_id'];
        }

        if(is_null($cardId) && isset($additionalData['additional_information']) && isset($additionalData['additional_information']['payplug_payments_customer_card_id'])){
          $cardId = $additionalData['additional_information']['payplug_payments_customer_card_id'];
        }

        $paymentInfo = $this->readPaymentModelArgument($observer);

        $paymentInfo->setAdditionalInformation(
            'payplug_payments_customer_card_id',
            $cardId
        );
    }
}
