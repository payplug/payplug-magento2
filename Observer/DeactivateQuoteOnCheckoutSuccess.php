<?php

namespace Payplug\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Payplug\Payments\Helper\Data as PayplugDataHelper;

class DeactivateQuoteOnCheckoutSuccess implements ObserverInterface
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly PayplugDataHelper $payplugDataHelper
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var OrderInterface $order */
        $order = $observer->getEvent()->getData('order');
        $paymentMethod = $order->getPayment()->getMethod();

        if (!$paymentMethod || $this->payplugDataHelper->isCodePayplugPayment($paymentMethod) === false) {
            return;
        }

        try {
            $quote = $this->cartRepository->getActive($order->getQuoteId());
            $quote->setIsActive(false);

            $this->cartRepository->save($quote);
        } catch (NoSuchEntityException) {
            // No active quote, nothing to do
        }
    }
}
