<?php

namespace Payplug\Payments\ViewModel;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Payplug\Payments\Helper\Data as PayplugDataHelper;

class CheckoutSuccessViewModel implements ArgumentInterface
{
    public function __construct(
        private readonly PayplugDataHelper $payplugDataHelper,
        private readonly CheckoutSession $checkoutSession
    ) {
    }

    public function needToInvalidateCustomerCartSection(): bool
    {
        $order = $this->checkoutSession->getLastRealOrder();
        $payment = $order->getPayment();

        if ($payment && $this->payplugDataHelper->isCodePayplugPaymentWithRedirect($payment->getMethod()) === true) {
            return true;
        }

        return false;
    }
}
