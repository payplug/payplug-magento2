<?php

namespace Payplug\Payments\Model\NewPaymentLink;

use Magento\Framework\Data\OptionSourceInterface;
use Payplug\Payments\Helper\OndemandOptions;

class SentBy implements OptionSourceInterface
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
     * Get sent by options
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        $sentByOptions = $this->onDemandHelper->getAvailableOndemandSentBy();

        foreach ($sentByOptions as $sentByKey => $sentByLabel) {
            $options[] = [
                'label' => $sentByLabel,
                'value' => $sentByKey,
            ];
        }

        return $options;
    }
}
