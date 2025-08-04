<?php

namespace Payplug\Payments\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Payplug\Payments\Helper\Data as PayplugDataHelper;

class CheckoutSuccessViewModel implements ArgumentInterface
{
    public function __construct(
        private readonly PayplugDataHelper $payplugDataHelper,
        private readonly OrderInterfaceFactory $orderFactory
    ) {
    }

    public function needToInvalidateCustomerCartSection(string $orderIncrementId): bool
    {
        $order = $this->orderFactory->create();
        $order->loadByIncrementId($orderIncrementId);

        $payment = $order->getPayment();

        if ($payment && $this->payplugDataHelper->isCodePayplugPayment($payment->getMethod()) === true) {
            return true;
        }

        return false;
    }
}
