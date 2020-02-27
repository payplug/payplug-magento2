<?php

namespace Payplug\Payments\Model\Config\Backend;

use Magento\Config\Model\Config\Backend\Serialized\ArraySerialized;

/**
 * Magento 2.1 uses serialize to store the value
 * From Magento 2.2, it uses json_encode
 * Force json_encode/json_decode to harmonize value storage between all Magento versions
 */
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
            if (empty($row['shipping_method'])) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Please select a shipping method')
                );
            }
            if (empty($row['shipping_type'])) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Please select a shipping type')
                );
            }
            if (!isset($row['shipping_period']) || $row['shipping_period'] === null || $row['shipping_period'] === '') {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Please select a shipping period')
                );
            }

            $shippingMethodCount++;
            $shippingMethods[$row['shipping_method']] = 1;
        }
        
        if (count($shippingMethods) !== $shippingMethodCount) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Duplicate shipping method configuration')
            );
        }

        if (isset($mappings['__empty'])) {
            unset($mappings['__empty']);
        }
        $mappings = json_encode($mappings);
        $this->setValue($mappings);

        return parent::beforeSave();
    }

    /**
     * @inheritdoc
     */
    protected function _afterLoad()
    {
        $value = $this->getValue();
        if (!is_array($value)) {
            $this->setValue(empty($value) ? false : json_decode($value, true));
        }
    }
}
