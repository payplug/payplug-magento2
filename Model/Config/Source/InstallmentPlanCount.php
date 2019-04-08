<?php

namespace Payplug\Payments\Model\Config\Source;

class InstallmentPlanCount implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => 2,
                'label' => __('2 times'),
            ],
            [
                'value' => 3,
                'label' => __('3 times'),
            ],
            [
                'value' => 4,
                'label' => __('4 times'),
            ],
        ];

        return $options;
    }
}
