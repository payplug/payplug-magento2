<?php

declare(strict_types=1);

namespace Payplug\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\StatusResolver;
use Payplug\Payments\Helper\Data as PayplugDataHelper;

class SetOrderStateToPendingPayment implements ObserverInterface
{
    /**
     * @param StatusResolver $statusResolver
     * @param PayplugDataHelper $payplugDataHelper
     */
    public function __construct(
        private readonly StatusResolver $statusResolver,
        private readonly PayplugDataHelper $payplugDataHelper
    ) {
    }

    /**
     * Set order state to pending payment if payment method is PayPlug
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var PaymentInterface $payment */
        $payment = $observer->getData('payment');

        if ($payment instanceof OrderPayment === false
            || $this->payplugDataHelper->isCodePayplugPayment($payment->getMethod()) === false
        ) {
            return;
        }

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $status = $this->statusResolver->getOrderStatusByState($order, Order::STATE_PENDING_PAYMENT);

        $order->setStatus($status);
        $order->setState(Order::STATE_PENDING_PAYMENT);
    }
}
