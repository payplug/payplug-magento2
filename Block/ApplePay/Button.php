<?php

namespace Payplug\Payments\Block\ApplePay;

use Magento\Framework\View\Element\Template;
use Payplug\Payments\Helper\ApplePay;

class Button extends Template
{
    /**
     * @var ApplePay
     */
    private $applePayHelper;

    /**
     * @param Template\Context $context
     * @param ApplePay         $applePayHelper
     * @param array            $data
     */
    public function __construct(
        Template\Context $context,
        ApplePay $applePayHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->applePayHelper = $applePayHelper;
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
