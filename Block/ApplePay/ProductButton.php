<?php

namespace Payplug\Payments\Block\ApplePay;

use Magento\Catalog\Api\Data\ProductInterface;

class ProductButton extends AbstractButton
{
    public function _toHtml(): string
    {
        if ($this->applePayHelper->canDisplayApplePay() && $this->applePayHelper->canDisplayApplePayOnProduct()) {
            return parent::_toHtml();
        }

        return '';
    }

    public function getProduct(): ?ProductInterface
    {
        return $this->registry->registry('product');
    }
}
