<?php

namespace Payplug\Payments\Model\Config\Backend;

use Magento\Config\Model\Config\Backend\Serialized\ArraySerialized;

class OneyShippingMapping extends ArraySerialized
{
    /**
     * @inheritdoc
     */
    public function beforeSave()
    {
        $mappings = $this->getValue();
        if (!is_array($mappings)) {
            return parent::beforeSave();
        }

        $shippingMethods = [];
        $shippingMethodCount = 0;
        foreach ($mappings as $rowKey => $row) {
            if (!isset($row['shipping_method'])) {
                continue;
            }
            $shippingMethodCount++;
            $shippingMethods[$row['shipping_method']] = 1;
        }
        
        if (count($shippingMethods) !== $shippingMethodCount) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Duplicate shipping method configuration')
            );
        }

        return parent::beforeSave();
    }
}
