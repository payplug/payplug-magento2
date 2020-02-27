<?php

namespace Payplug\Payments\Block\Adminhtml\Config\OneyShippingMapping;

use Magento\Framework\View\Element\Html\Select;
use Magento\Framework\View\Element\Context;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Config;

class ShippingMethodColumn extends Select
{
    /**
     * @var Config
     */
    private $shippingConfig;

    /**
     * @param Context $context
     * @param Config  $shippingConfig
     * @param array   $data
     */
    public function __construct(
        Context $context,
        Config $shippingConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->shippingConfig = $shippingConfig;
    }

    /**
     * @return array
     */
    private function getShippingMethods()
    {
        $carriers = $this->shippingConfig->getAllCarriers();
        $carrierConfig = [];
        $carrierConfig[] = [
            'label' => __('Select shipping method'),
            'value' => '',
        ];
        /** @var CarrierInterface $carrierModel */
        foreach ($carriers as $carrierCode => $carrierModel) {
            $allowedMethods = $carrierModel->getAllowedMethods();
            foreach ($allowedMethods as $methodCode => $methodLabel) {
                $carrierConfig[] = [
                    'label' => sprintf(
                        '%s - %s',
                        $this->_scopeConfig->getValue('carriers/' . $carrierCode . '/title'),
                        $methodLabel
                    ),
                    'value' => $carrierCode . '_' . $methodCode,
                ];
            }
        }

        return $carrierConfig;
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
            $this->setOptions($this->getShippingMethods());
        }
        return parent::_toHtml();
    }
}
