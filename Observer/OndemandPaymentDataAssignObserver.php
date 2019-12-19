<?php

namespace Payplug\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class OndemandPaymentDataAssignObserver extends AbstractDataAssignObserver
{
    /**
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

        $paymentInfo = $this->readPaymentModelArgument($observer);

        $additionalInfos = [
            'sent_by',
            'sent_by_value',
            'language',
            'description',
        ];

        foreach ($additionalInfos as $additionalInfo) {
            if (empty($additionalData[$additionalInfo])) {
                continue;
            }
            $paymentInfo->setAdditionalInformation(
                $additionalInfo,
                $additionalData[$additionalInfo]
            );
        }
    }
}
