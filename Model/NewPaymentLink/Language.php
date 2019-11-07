<?php

namespace Payplug\Payments\Model\NewPaymentLink;

use Magento\Framework\Data\OptionSourceInterface;
use Payplug\Payments\Model\Order\Payment;

class Language implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        
        $languageOptions = Payment::getAvailableOndemandLanguage();

        foreach ($languageOptions as $languageKey => $languageLabel) {
            $options[] = [
                'label' => $languageLabel,
                'value' => $languageKey,
            ];
        }

        return $options;
    }
}
