<?php

namespace Payplug\Payments\Helper;

use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\FilterBuilderFactory;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilderFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteriaInterfaceFactory;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Api\SortOrderBuilderFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\PaymentException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\GridInterface;
use Payplug\Payments\Gateway\Config\InstallmentPlan;
use Payplug\Payments\Gateway\Config\Ondemand;
use Payplug\Payments\Gateway\Config\Oney;
use Payplug\Payments\Gateway\Config\Standard;
use Payplug\Payments\Model\Order\Payment;
use Payplug\Payments\Model\Order\Processing;
use Payplug\Payments\Model\Order\ProcessingFactory as PayplugOrderProcessingFactory;
use Payplug\Payments\Model\OrderInstallmentPlanRepository;
use Payplug\Payments\Model\OrderPaymentRepository;
use Payplug\Payments\Model\OrderProcessingRepository;
use Payplug\Resource\IVerifiableAPIResource;
use Payplug\Payments\Helper\Ondemand as OndemandHelper;

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
     * @var SearchCriteriaInterfaceFactory
     */
    private $searchCriteriaInterfaceFactory;

    /**
     * @var FilterBuilderFactory
     */
    private $filterBuilderFactory;

    /**
     * @var FilterGroupBuilderFactory
     */
    private $filterGroupBuilderFactory;

    /**
     * @var SortOrderBuilderFactory
     */
    private $sortOrderBuilderFactory;

    /**
     * @var OndemandHelper
     */
    private $ondemandHelper;

    /**
     * @param Context                                      $context
     * @param \Payplug\Payments\Model\Order\PaymentFactory $paymentFactory
     * @param OrderPaymentRepository                       $orderPaymentRepository
     * @param OrderRepository                              $orderRepository
     * @param PayplugOrderProcessingFactory                $orderProcessingFactory
     * @param OrderProcessingRepository                    $orderProcessingRepository
     * @param OrderInstallmentPlanRepository               $orderInstallmentPlanRepository
     * @param GridInterface                                $salesGrid
     * @param SearchCriteriaInterfaceFactory               $searchCriteriaInterfaceFactory
     * @param FilterBuilderFactory                         $filterBuilderFactory
     * @param FilterGroupBuilderFactory                    $filterGroupBuilderFactory
     * @param SortOrderBuilderFactory                      $sortOrderBuilderFactory
     * @param OndemandHelper                               $ondemandHelper
     */
    public function __construct(
        Context $context,
        \Payplug\Payments\Model\Order\PaymentFactory $paymentFactory,
        OrderPaymentRepository $orderPaymentRepository,
        OrderRepository $orderRepository,
        PayplugOrderProcessingFactory $orderProcessingFactory,
        OrderProcessingRepository $orderProcessingRepository,
        OrderInstallmentPlanRepository $orderInstallmentPlanRepository,
        GridInterface $salesGrid,
        SearchCriteriaInterfaceFactory $searchCriteriaInterfaceFactory,
        FilterBuilderFactory $filterBuilderFactory,
        FilterGroupBuilderFactory $filterGroupBuilderFactory,
        SortOrderBuilderFactory $sortOrderBuilderFactory,
        OndemandHelper $ondemandHelper
    ) {
        parent::__construct($context);
        $this->paymentFactory = $paymentFactory;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->orderRepository = $orderRepository;
        $this->orderProcessingFactory = $orderProcessingFactory;
        $this->orderProcessingRepository = $orderProcessingRepository;
        $this->orderInstallmentPlanRepository = $orderInstallmentPlanRepository;
        $this->salesGrid = $salesGrid;
        $this->searchCriteriaInterfaceFactory = $searchCriteriaInterfaceFactory;
        $this->filterBuilderFactory = $filterBuilderFactory;
        $this->filterGroupBuilderFactory = $filterGroupBuilderFactory;
        $this->sortOrderBuilderFactory = $sortOrderBuilderFactory;
        $this->ondemandHelper = $ondemandHelper;
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
     * @param string $paymentId
     *
     * @return \Payplug\Payments\Model\Order\Payment
     */
    public function getOrderPaymentByPaymentId($paymentId)
    {
        return $this->orderPaymentRepository->get($paymentId, 'payment_id');
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
     * @param int $orderId
     *
     * @return \Payplug\Payments\Model\Order\Payment|null
     */
    public function getOrderLastPayment($orderId)
    {
        $orderPayments = $this->getOrderPayments($orderId);

        return array_shift($orderPayments);
    }

    /**
     * @param int $orderId
     *
     * @return array|Payment[]
     */
    public function getOrderPayments($orderId)
    {
        /** @var SearchCriteriaInterface $criteria */
        $criteria = $this->searchCriteriaInterfaceFactory->create();

        $filterGroups = [
            $this->getFieldFilter('order_id', $orderId),
        ];

        $criteria->setFilterGroups($filterGroups);

        /** @var SortOrder $sortOrder */
        /** @var SortOrderBuilder $sortBuilder */
        $sortBuilder = $this->sortOrderBuilderFactory->create();
        $sortBuilder->setField('entity_id');
        $sortBuilder->setDescendingDirection();
        $sortOrder = $sortBuilder->create();

        $criteria->setSortOrders([$sortOrder]);

        $result = $this->orderPaymentRepository->getList($criteria);
        $orderPayments = $result->getItems();

        return $orderPayments;
    }

    /**
     * @param string $field
     * @param mixed  $value
     * @param string $type
     *
     * @return FilterGroup
     */
    private function getFieldFilter($field, $value, $type = 'eq')
    {
        /** @var Filter $filter */
        /** @var FilterBuilder $filterBuilder */
        $filterBuilder = $this->filterBuilderFactory->create();
        $filterBuilder->setField($field);
        $filterBuilder->setConditionType($type);
        $filterBuilder->setValue($value);
        $filter = $filterBuilder->create();

        /** @var FilterGroup $filterGroup */
        /** @var FilterGroupBuilder $filterGroupBuilder */
        $filterGroupBuilder = $this->filterGroupBuilderFactory->create();
        $filterGroupBuilder->addFilter($filter);
        $filterGroup = $filterGroupBuilder->create();

        return $filterGroup;
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
            Ondemand::METHOD_CODE => [
                Order::STATE_PAYMENT_REVIEW
            ],
            Oney::METHOD_CODE => [
                Order::STATE_PAYMENT_REVIEW
            ],
        ];
        if (!in_array($order->getState(), $allowedStates[$order->getPayment()->getMethod()])) {
            return false;
        }

        return true;
    }

    /**
     * Check if order's payment can be updated
     *
     * @param Order $order
     *
     * @return bool
     */
    public function canSendNewPaymentLink($order)
    {
        if ($order->getPayment() === false) {
            return false;
        }

        if ($order->getPayment()->getMethod() != Ondemand::METHOD_CODE) {
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
            if (!empty($orderStatus) && $orderStatus !== $order->getStatus()) {
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

            $this->updateOrderPayment($order);
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
     * @return bool
     */
    public function isOrderValidated($order)
    {
        if ($order->getState() == Order::STATE_PROCESSING || $order->getState() == Order::STATE_COMPLETE) {
            return true;
        }

        // If Oney payment is still being reviewed, order is validated but still in Payment Review state
        if ($order->getPayment()->getMethod() == Oney::METHOD_CODE && $order->getState() == Order::STATE_PAYMENT_REVIEW) {
            return true;
        }

        return false;
    }

    /**
     * @param Order $order
     * @param array $paymentLinkData
     *
     * @return Order
     *
     * @throws PaymentException
     */
    public function sendNewPaymentLink($order, $paymentLinkData)
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
            if (!$this->canSendNewPaymentLink($order)) {
                $this->orderProcessingRepository->delete($orderProcessing);

                return $order;
            }

            $lastOrderPayment = $this->getOrderLastPayment($order->getIncrementId());
            $this->ondemandHelper->sendNewPaymentLink($order, $lastOrderPayment, $paymentLinkData);

            $this->orderRepository->save($order);
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
        return $code == Standard::METHOD_CODE ||
            $code == \Payplug\Payments\Gateway\Config\InstallmentPlan::METHOD_CODE ||
            $code == Ondemand::METHOD_CODE ||
            $code == Oney::METHOD_CODE;
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
    private function updateOrderPayment($order)
    {
        $payment = $order->getPayment();
        $transactionId = $payment->getLastTransId();
        $isInstallmentPlan = $order->getPayment()->getMethod() == InstallmentPlan::METHOD_CODE;

        if (count($order->getInvoiceCollection()) > 0 && !$isInstallmentPlan) {
            $order->getPayment()->update();

            return;
        }

        $method = $payment->getMethodInstance();
        $method->setStore($order->getStoreId());
        $method->fetchTransactionInfo($payment, $transactionId);

        if ($payment->getIsTransactionApproved() || ($isInstallmentPlan && !$payment->getTransactionPending())) {
            if (count($order->getInvoiceCollection()) === 0) {
                $invoice = $order->prepareInvoice();
                $invoice->register();
                $order->addRelatedObject($invoice);
                $invoice->setTransactionId($transactionId);

                if (!$isInstallmentPlan) {
                    $order->setState(Order::STATE_PROCESSING);
                    $invoice->pay();
                    $payment->setBaseAmountPaidOnline($order->getBaseGrandTotal());
                    $message = __('Registered update about approved payment.') . ' ' . __('Transaction ID: "%1"', $transactionId);
                    $order->addStatusToHistory(
                        $order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING),
                        $message
                    );
                } else {
                    // Order amounts and status history are already handled
                    // in \Payplug\Payments\Gateway\Response\InstallmentPlan\FetchTransactionInformationHandler::handle
                    $invoice->setState(Order\Invoice::STATE_PAID);
                }
            }
        } elseif ($payment->getIsTransactionDenied()) {
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
