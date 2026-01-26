<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Ui\Component\Listing\Column\InstallmentPlanStatus;

use Payplug\Payments\Helper\Data;

class Options implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @var array
     */
    private $options;

    /**
     * @var Data
     */
    private $payplugHelper;

    /**
     * @param Data $payplugHelper
     */
    public function __construct(Data $payplugHelper)
    {
        $this->payplugHelper = $payplugHelper;
    }

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        if ($this->options === null) {
            $options = [];
            $labels = $this->payplugHelper->getInstallmentPlanStatusesLabel();
            foreach ($labels as $code => $label) {
                $options[$code] = ['value' => $code, 'label' => __($label)];
            }
            $this->options = $options;
        }

        return $this->options;
    }
}
