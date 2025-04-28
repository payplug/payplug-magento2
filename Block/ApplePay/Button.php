<?php

declare(strict_types=1);

namespace Payplug\Payments\Block\ApplePay;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Payplug\Payments\Helper\ApplePay;

class Button extends Template
{
    public function __construct(
        Context $context,
        private ApplePay $applePayHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritdoc
     */
    public function _toHtml(): string
    {
        if ($this->applePayHelper->canDisplayApplePay() && $this->applePayHelper->canDisplayApplePayOnCart()) {
            return parent::_toHtml();
        }

        return '';
    }
}
