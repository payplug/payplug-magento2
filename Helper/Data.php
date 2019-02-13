<?php

namespace Payplug\Payments\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;
use Payplug\Payments\Model\OrderPaymentRepository;
use Payplug\Payments\Model\Payment\AbstractPaymentMethod;
use Payplug\Resource\Payment;

class Data extends AbstractHelper
{
    /**
     * @var \Payplug\Payments\Model\Order\PaymentFactory
     */
    protected $paymentFactory;

    /**
     * @var OrderPaymentRepository
     */
    protected $orderPaymentRepository;

    /**
     * @param Context                                      $context
     * @param \Payplug\Payments\Model\Order\PaymentFactory $paymentFactory
     * @param OrderPaymentRepository                       $orderPaymentRepository
     */
    public function __construct(
        Context $context,
        \Payplug\Payments\Model\Order\PaymentFactory $paymentFactory,
        OrderPaymentRepository $orderPaymentRepository
    ) {
        parent::__construct($context);
        $this->paymentFactory = $paymentFactory;
        $this->orderPaymentRepository = $orderPaymentRepository;
    }

    /**
     * @param int $orderId
     *
     * @return \Payplug\Payments\Model\Order\Payment
     */
    public function getOrderPayment($orderId)
    {
        return $this->orderPaymentRepository->get($orderId, 'order_id');
    }

    /**
     * @param Payment $payment
     *
     * @return string
     */
    public function getPaymentErrorMessage($payment)
    {
        if ($payment->failure === null) {
            return '';
        }

        if ($payment->failure->message) {
            return $payment->failure->message;
        }

        return '';
    }

    /**
     * Check if order's payment can be updated
     *
     * @param Order $order
     *
     * @return bool
     */
    public function canUpdatePayment($order)
    {
        if ($order->getPayment() === false) {
            return false;
        }

        if (!$order->getPayment()->getMethodInstance() instanceof AbstractPaymentMethod) {
            return false;
        }

        $finalStates = [Order::STATE_CANCELED, Order::STATE_CLOSED];
        if (in_array($order->getState(), $finalStates)) {
            return false;
        }

        return true;
    }
}
