<?php

namespace Payplug\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Api\Data\CartInterface;
use Payplug\Payments\Helper\Data as PayplugDataHelper;

class KeepQuoteActiveOnPlaceOrder implements ObserverInterface
{
    public function __construct(
        private readonly PayplugDataHelper $payplugDataHelper
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var CartInterface $quote */
        $quote = $observer->getEvent()->getData('quote');
        $paymentMethod = $quote->getPayment()->getMethod();

        if ($paymentMethod && $this->payplugDataHelper->isCodePayplugPaymentWithRedirect($paymentMethod) === true) {
            // Keep quote active, will be desactivate on success page only
            $quote->setIsActive(true);
        }
    }
}
