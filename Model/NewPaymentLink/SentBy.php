<?php

namespace Payplug\Payments\Model\NewPaymentLink;

use Magento\Framework\Data\OptionSourceInterface;
use Payplug\Payments\Model\Order\Payment;

class SentBy implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        $sentByOptions = Payment::getAvailableOndemandSentBy();

        foreach ($sentByOptions as $sentByKey => $sentByLabel) {
            $options[] = [
                'label' => $sentByLabel,
                'value' => $sentByKey,
            ];
        }

        return $options;
    }
}
