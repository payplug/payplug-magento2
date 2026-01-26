<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Model\NewPaymentLink;

use Magento\Framework\Data\OptionSourceInterface;
use Payplug\Payments\Helper\OndemandOptions;

class Language implements OptionSourceInterface
{
    /**
     * @var OndemandOptions
     */
    private $onDemandHelper;

    /**
     * @param OndemandOptions $onDemandHelper
     */
    public function __construct(OndemandOptions $onDemandHelper)
    {
        $this->onDemandHelper = $onDemandHelper;
    }

    /**
     * Get language options
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];

        $languageOptions = $this->onDemandHelper->getAvailableOndemandLanguage();

        foreach ($languageOptions as $languageKey => $languageLabel) {
            $options[] = [
                'label' => $languageLabel,
                'value' => $languageKey,
            ];
        }

        return $options;
    }
}
