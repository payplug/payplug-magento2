<?php

namespace Payplug\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Sales\Model\Order;
use Magento\Checkout\Model\Session\Proxy;

class PaymentReturnObserver implements ObserverInterface
{

    /**
     * @var Proxy
     */
    private $checkoutSession;

    /**
     * @param Logger $checkoutSession
     */
    public function __construct( Proxy $checkoutSession )
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