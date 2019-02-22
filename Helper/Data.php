<?php

namespace Payplug\Payments\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Payplug\Payments\Model\Order\Processing;
use Payplug\Payments\Model\Order\ProcessingFactory as PayplugOrderProcessingFactory;
use Payplug\Payments\Model\OrderPaymentRepository;
use Payplug\Payments\Model\OrderProcessingRepository;
use Payplug\Resource\Payment;

class Data extends AbstractHelper
{
    /**
     * @var \Payplug\Payments\Model\Order\PaymentFactory
     */
    private $paymentFactory;

    /**
     * @var OrderPaymentRepository
     */
    private $orderPaymentRepository;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var PayplugOrderProcessingFactory
     */
    private $orderProcessingFactory;

    /**
     * @var OrderProcessingRepository
     */
    private $orderProcessingRepository;

    /**
     * @param Context                                      $context
     * @param \Payplug\Payments\Model\Order\PaymentFactory $paymentFactory
     * @param OrderPaymentRepository                       $orderPaymentRepository
     * @param OrderRepository                              $orderRepository
     * @param PayplugOrderProcessingFactory                $orderProcessingFactory
     * @param OrderProcessingRepository                    $orderProcessingRepository
     */
    public function __construct(
        Context $context,
        \Payplug\Payments\Model\Order\PaymentFactory $paymentFactory,
        OrderPaymentRepository $orderPaymentRepository,
        OrderRepository $orderRepository,
        PayplugOrderProcessingFactory $orderProcessingFactory,
        OrderProcessingRepository $orderProcessingRepository
    ) {
        parent::__construct($context);
        $this->paymentFactory = $paymentFactory;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->orderRepository = $orderRepository;
        $this->orderProcessingFactory = $orderProcessingFactory;
        $this->orderProcessingRepository = $orderProcessingRepository;
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

        if ($order->getPayment()->getMethod() != 'payplug_payments_standard') {
            return false;
        }

        if ($order->getState() != Order::STATE_PAYMENT_REVIEW) {
            return false;
        }

        return true;
    }

    /**
     * @param Order $order
     * @param bool  $save
     */
    public function updateOrderStatus($order, $save = true)
    {
        $field = null;
        if ($order->getState() == Order::STATE_PROCESSING) {
            $field = 'processing_order_status';
        } elseif ($order->getState() == Order::STATE_CANCELED) {
            $field = 'canceled_order_status';
        }
        if ($field !== null) {
            $orderStatus = $order->getPayment()->getMethodInstance()->getConfigData($field, $order->getStoreId());
            if ($orderStatus !== $order->getStatus()) {
                $order->addStatusToHistory($orderStatus, __('Custom Payplug Payments status'));
                if ($save) {
                    $this->orderRepository->save($order);
                }
            }
        }
    }

    /**
     * @param Order $order
     */
    public function updateOrder($order)
    {
        try {
            $orderProcessing = $this->orderProcessingRepository->get($order->getId(), 'order_id');
            $createdAt = new \DateTime($orderProcessing->getCreatedAt());
            if ($createdAt > new \DateTime("now - 1 min")) {
                // Order is currently being processed
                return;
            }
            // Order has been set as processing for more than a minute
            // Delete and recreate a new flag
            $this->orderProcessingRepository->delete($orderProcessing);
        } catch (NoSuchEntityException $e) {
            // Order is not currently being processed
            // Create a new flag to block concurrent process
        }

        try {
            $orderProcessing = $this->createOrderProcessing($order);
        } catch (\Exception $e) {
            return;
        }

        try {
            $order = $this->orderRepository->get($order->getId());
            if ($order->getState() != Order::STATE_PAYMENT_REVIEW) {
                $this->orderProcessingRepository->delete($orderProcessing);
                return;
            }

            $order->getPayment()->update();
            $this->updateOrderStatus($order, false);
            $this->orderRepository->save($order);
        } finally {
            $this->orderProcessingRepository->delete($orderProcessing);
        }
    }

    /**
     * @param Order $order
     *
     * @return Processing
     */
    private function createOrderProcessing($order)
    {
        /** @var Processing $orderProcessing */
        $orderProcessing = $this->orderProcessingFactory->create();
        $orderProcessing->setOrderId($order->getId());
        $date = new \DateTime();
        $orderProcessing->setCreatedAt($date->format('Y-m-d H:i:s'));
        $this->orderProcessingRepository->save($orderProcessing);
        return $orderProcessing;
    }
}
