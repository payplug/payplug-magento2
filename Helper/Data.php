<?php

namespace Payplug\Payments\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\GridInterface;
use Payplug\Payments\Gateway\Config\InstallmentPlan;
use Payplug\Payments\Gateway\Config\Standard;
use Payplug\Payments\Model\Order\Processing;
use Payplug\Payments\Model\Order\ProcessingFactory as PayplugOrderProcessingFactory;
use Payplug\Payments\Model\OrderInstallmentPlanRepository;
use Payplug\Payments\Model\OrderPaymentRepository;
use Payplug\Payments\Model\OrderProcessingRepository;
use Payplug\Payments\Model\Payment\AbstractPaymentMethod;
use Payplug\Resource\IVerifiableAPIResource;

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
     * @var OrderInstallmentPlanRepository
     */
    private $orderInstallmentPlanRepository;

    /**
     * @var GridInterface
     */
    private $salesGrid;

    /**
     * @param Context                                      $context
     * @param \Payplug\Payments\Model\Order\PaymentFactory $paymentFactory
     * @param OrderPaymentRepository                       $orderPaymentRepository
     * @param OrderRepository                              $orderRepository
     * @param PayplugOrderProcessingFactory                $orderProcessingFactory
     * @param OrderProcessingRepository                    $orderProcessingRepository
     * @param OrderInstallmentPlanRepository               $orderInstallmentPlanRepository
     */
    public function __construct(
        Context $context,
        \Payplug\Payments\Model\Order\PaymentFactory $paymentFactory,
        OrderPaymentRepository $orderPaymentRepository,
        OrderRepository $orderRepository,
        PayplugOrderProcessingFactory $orderProcessingFactory,
        OrderProcessingRepository $orderProcessingRepository,
        OrderInstallmentPlanRepository $orderInstallmentPlanRepository,
        GridInterface $salesGrid
    ) {
        parent::__construct($context);
        $this->paymentFactory = $paymentFactory;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->orderRepository = $orderRepository;
        $this->orderProcessingFactory = $orderProcessingFactory;
        $this->orderProcessingRepository = $orderProcessingRepository;
        $this->orderInstallmentPlanRepository = $orderInstallmentPlanRepository;
        $this->salesGrid = $salesGrid;
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
     * @param int $orderId
     *
     * @return \Payplug\Payments\Model\Order\InstallmentPlan
     */
    public function getOrderInstallmentPlan($orderId)
    {
        return $this->orderInstallmentPlanRepository->get($orderId, 'order_id');
    }

    /**
     * @param IVerifiableAPIResource $resource
     *
     * @return string
     */
    public function getPaymentErrorMessage($resource)
    {
        if ($resource->failure === null) {
            return '';
        }

        if ($resource->failure->message) {
            return $resource->failure->message;
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

        if (!$this->isCodePayplugPayment($order->getPayment()->getMethod())) {
            return false;
        }

        $allowedStates = [
            Standard::METHOD_CODE => [
                Order::STATE_PAYMENT_REVIEW
            ],
            InstallmentPlan::METHOD_CODE => [
                Order::STATE_PAYMENT_REVIEW,
                Order::STATE_PROCESSING,
                Order::STATE_COMPLETE
            ],
        ];
        if (!in_array($order->getState(), $allowedStates[$order->getPayment()->getMethod()])) {
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
     *
     * @return Order
     */
    public function updateOrder($order)
    {
        try {
            $orderProcessing = $this->orderProcessingRepository->get($order->getId(), 'order_id');
            $createdAt = new \DateTime($orderProcessing->getCreatedAt());
            if ($createdAt > new \DateTime("now - 1 min")) {
                // Order is currently being processed
                return $order;
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
            return $order;
        }

        try {
            $order = $this->orderRepository->get($order->getId());
            if (!$this->canUpdatePayment($order)) {
                $this->orderProcessingRepository->delete($orderProcessing);
                return $order;
            }

            if ($order->getPayment()->getMethod() == Standard::METHOD_CODE) {
                $order->getPayment()->update();
            } elseif ($order->getPayment()->getMethod() == InstallmentPlan::METHOD_CODE) {
                $this->updateInstallmentPlanPayment($order);
            }
            $this->updateOrderStatus($order, false);
            $this->orderRepository->save($order);
            $this->refreshSalesGrid($order->getId());
        } finally {
            $this->orderProcessingRepository->delete($orderProcessing);
        }

        return $order;
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

    /**
     * Check if order's installment plan can be aborted
     *
     * @param Order $order
     *
     * @return bool
     */
    public function canAbortInstallmentPlan($order)
    {
        if ($order->getPayment() === false) {
            return false;
        }

        if ($order->getPayment()->getMethod() != \Payplug\Payments\Gateway\Config\InstallmentPlan::METHOD_CODE) {
            return false;
        }

        return true;
    }

    /**
     * @return array
     */
    public function getInstallmentPlanStatusesLabel()
    {
        return [
            \Payplug\Payments\Model\Order\InstallmentPlan::STATUS_NEW => 'New',
            \Payplug\Payments\Model\Order\InstallmentPlan::STATUS_ONGOING => 'Ongoing',
            \Payplug\Payments\Model\Order\InstallmentPlan::STATUS_ABORTED => 'Aborted',
            \Payplug\Payments\Model\Order\InstallmentPlan::STATUS_COMPLETE => 'Complete',
        ];
    }

    /**
     * @param string $code
     *
     * @return bool
     */
    public function isCodePayplugPayment($code)
    {
        return $code == Standard::METHOD_CODE || $code == \Payplug\Payments\Gateway\Config\InstallmentPlan::METHOD_CODE;
    }

    /**
     * @param \Payplug\Payments\Model\Order\InstallmentPlan $orderInstallmentPlan
     * @param \Payplug\Resource\InstallmentPlan             $installmentPlan
     */
    public function updateInstallmentPlanStatus($orderInstallmentPlan, $installmentPlan)
    {
        $status = \Payplug\Payments\Model\Order\InstallmentPlan::STATUS_NEW;
        if ($installmentPlan->is_active && !$installmentPlan->is_fully_paid) {
            $status = \Payplug\Payments\Model\Order\InstallmentPlan::STATUS_ONGOING;
        } elseif (!$installmentPlan->is_active) {
            $status = \Payplug\Payments\Model\Order\InstallmentPlan::STATUS_COMPLETE;
            if (!$installmentPlan->is_fully_paid) {
                $status = \Payplug\Payments\Model\Order\InstallmentPlan::STATUS_ABORTED;
            }
        }

        $orderInstallmentPlan->setStatus($status);
        $this->orderInstallmentPlanRepository->save($orderInstallmentPlan);
    }

    /**
     * @param int $orderId
     */
    public function refreshSalesGrid($orderId)
    {
        $this->salesGrid->refresh($orderId);
    }

    /**
     * @param Order $order
     */
    private function updateInstallmentPlanPayment($order)
    {
        $payment = $order->getPayment();
        $transactionId = $payment->getLastTransId();

        $method = $payment->getMethodInstance();
        $method->setStore($order->getStoreId());
        $method->fetchTransactionInfo($payment, $transactionId);

        if ($payment->getIsTransactionDenied()) {
            $this->cancelOrderAndInvoice($order);
        }
    }

    /**
     * @param Order $order
     */
    public function cancelOrderAndInvoice($order)
    {
        if ($order->getState() != Order::STATE_PAYMENT_REVIEW) {
            return;
        }

        // Manually execute Payment::cancelInvoiceAndRegisterCancellation which is protected
        $orderInvoice = null;
        foreach ($order->getInvoiceCollection() as $invoice) {
            if ($invoice->getState() == \Magento\Sales\Model\Order\Invoice::STATE_OPEN &&
                $invoice->load($invoice->getId())
            ) {
                $orderInvoice = $invoice;
            }
        }
        if ($orderInvoice instanceof Order\Invoice) {
            $orderInvoice->cancel();
            $order->addRelatedObject($orderInvoice);
        }
        $order->registerCancellation('Payplug payment was not successfull.', false);
        $this->updateOrderStatus($order, false);
        $this->orderRepository->save($order);
    }
}
