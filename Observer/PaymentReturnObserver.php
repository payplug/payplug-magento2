<?php

namespace Payplug\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Sales\Model\Order;
use Magento\Checkout\Model\Session as CheckoutSession;

class PaymentReturnObserver implements ObserverInterface
{

    /**
     * @var Proxy
     */
    protected $checkoutSession;

    /**
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        CheckoutSession $checkoutSession
    )
    {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @inheritdoc
     */
    public function execute(EventObserver $observer)
    {

        $lastRealOrder = $this->checkoutSession->getLastRealOrder();
        if ($lastRealOrder->getPayment()) {

            if ($lastRealOrder->getData('state') === 'payment_review' && $lastRealOrder->getData('status') === 'payment_review') {
                $this->checkoutSession->restoreQuote();
            }
        }

        return true;

    }

}