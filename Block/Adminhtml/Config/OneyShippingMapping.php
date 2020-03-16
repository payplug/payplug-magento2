<?php
namespace Payplug\Payments\Block\Adminhtml\Config;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Payplug\Payments\Block\Adminhtml\Config\OneyShippingMapping\ShippingMethodColumn;
use Payplug\Payments\Block\Adminhtml\Config\OneyShippingMapping\ShippingTypeColumn;
use Vendor\Module\Block\Adminhtml\Form\Field\TaxColumn;

/**
 * Class Ranges
 */
class OneyShippingMapping extends AbstractFieldArray
{
    /**
     * @var ShippingMethodColumn
     */
    private $shippingMethodRenderer;

    /**
     * @var ShippingTypeColumn
     */
    private $shippingTypeRenderer;

    /**
     * Prepare rendering the new field by adding all the needed columns
     */
    protected function _prepareToRender()
    {
        $this->addColumn('shipping_method', [
            'label' => __('Shipping Method'),
            'renderer' => $this->getShippingMethodRenderer(),
        ]);
        $this->addColumn('shipping_type', [
            'label' => __('Shipping Type'),
            'renderer' => $this->getShippingTypeRenderer(),
        ]);
        $this->addColumn('shipping_period', [
            'label' => __('Shipping Period'),
            'class' => 'required-entry validate-digits validate-zero-or-greater'
        ]);
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Shipping Mapping');
    }

    /**
     * Prepare existing row data object
     *
     * @param DataObject $row
     * @throws LocalizedException
     */
    protected function _prepareArrayRow(DataObject $row)
    {
        $options = [];

        $shippingMethod = $row->getShippingMethod();
        if ($shippingMethod !== null) {
            $optionKey = 'option_' . $this->getShippingMethodRenderer()->calcOptionHash($shippingMethod);
            $options[$optionKey] = 'selected="selected"';
        }
        $shippingType = $row->getShippingType();
        if ($shippingType !== null) {
            $optionKey = 'option_' . $this->getShippingTypeRenderer()->calcOptionHash($shippingType);
            $options[$optionKey] = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }

    /**
     * @return ShippingMethodColumn
     * @throws LocalizedException
     */
    private function getShippingMethodRenderer()
    {
        if (!$this->shippingMethodRenderer) {
            $this->shippingMethodRenderer = $this->getLayout()->createBlock(
                ShippingMethodColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true, 'class' => 'required-entry']]
            );
        }
        return $this->shippingMethodRenderer;
    }

    /**
     * @return ShippingMethodColumn
     * @throws LocalizedException
     */
    private function getShippingTypeRenderer()
    {
        if (!$this->shippingTypeRenderer) {
            $this->shippingTypeRenderer = $this->getLayout()->createBlock(
                ShippingTypeColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true, 'class' => 'required-entry oney-shipping-mapping-type']]
            );
        }
        return $this->shippingTypeRenderer;
    }
}
