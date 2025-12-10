<?php

namespace Payplug\Payments\Block\ApplePay;

class CartButton extends AbstractButton
{
    /**
     * Display ApplePay button only if it's possible to display it on cart page'
     *
     * @return string
     */
    public function _toHtml(): string
    {
        if ($this->applePayHelper->canDisplayApplePay() && $this->applePayHelper->canDisplayApplePayOnCart()) {
            return parent::_toHtml();
        }

        return '';
    }
}
