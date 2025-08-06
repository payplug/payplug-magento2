<?php

namespace Payplug\Payments\Block\ApplePay;

class CartButton extends AbstractButton
{
    public function _toHtml(): string
    {
        if ($this->applePayHelper->canDisplayApplePay() && $this->applePayHelper->canDisplayApplePayOnCart()) {
            return parent::_toHtml();
        }

        return '';
    }
}
