<?php

namespace Payplug\Payments\Block\Adminhtml\Config\OneyShippingMapping;

use Magento\Framework\View\Element\Html\Select;

class ShippingTypeColumn extends Select
{
    /**
     * @return array
     */
    private function getShippingTypes()
    {
        return [
            ['label' => __('Select shipping type'), 'value' => ''],
            ['label' => __('Store Pickup'), 'value' => 'storepickup'],
            ['label' => __('Network Pickup'), 'value' => 'networkpickup'],
            ['label' => __('Travel Pickup'), 'value' => 'travelpickup'],
            ['label' => __('Carrier'), 'value' => 'carrier'],
        ];
    }

    /**
     * Set "name" for <select> element
     *
     * @param string $value
     * @return $this
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    /**
     * Set "id" for <select> element
     *
     * @param $value
     * @return $this
     */
    public function setInputId($value)
    {
        return $this->setId($value);
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->getShippingTypes());
        }
        return parent::_toHtml();
    }
}
