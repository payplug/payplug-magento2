<?php

declare(strict_types=1);

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
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\PaymentException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\GridInterface;
use Payplug\Exception\HttpException;
use Payplug\Payments\Exception\OrderAlreadyProcessingException;
use Payplug\Payments\Gateway\Config\Amex;
use Payplug\Payments\Gateway\Config\ApplePay;
use Payplug\Payments\Gateway\Config\Bancontact;
use Payplug\Payments\Gateway\Config\Ideal;
use Payplug\Payments\Gateway\Config\InstallmentPlan;
use Payplug\Payments\Gateway\Config\Mybank;
use Payplug\Payments\Gateway\Config\Ondemand;
use Payplug\Payments\Gateway\Config\Oney;
use Payplug\Payments\Gateway\Config\OneyWithoutFees;
use Payplug\Payments\Gateway\Config\Satispay;
use Payplug\Payments\Gateway\Config\Standard;
use Payplug\Payments\Helper\Ondemand as OndemandHelper;
use Payplug\Payments\Model\Order\InstallmentPlan as OrderInstallmentPlan;
use Payplug\Payments\Model\Order\Payment;
use Payplug\Payments\Model\Order\PaymentFactory;
use Payplug\Payments\Model\Order\Processing;
use Payplug\Payments\Model\Order\ProcessingFactory as PayplugOrderProcessingFactory;
use Payplug\Payments\Model\OrderInstallmentPlanRepository;
use Payplug\Payments\Model\OrderPaymentRepository;
use Payplug\Payments\Model\OrderProcessingRepository;
use Payplug\Resource\InstallmentPlan as ResourceInstallmentPlan;
use Payplug\Resource\IVerifiableAPIResource;
use Payplug\Resource\Payment as ResourcePayment;

class Data extends AbstractHelper
{
    public function __construct(
        Context $context,
        private PaymentFactory $paymentFactory,
        private OrderPaymentRepository $orderPaymentRepository,
        private OrderRepository $orderRepository,
        private PayplugOrderProcessingFactory $orderProcessingFactory,
        private OrderProcessingRepository $orderProcessingRepository,
        private OrderInstallmentPlanRepository $orderInstallmentPlanRepository,
        private GridInterface $salesGrid,
        private SearchCriteriaInterfaceFactory $searchCriteriaInterfaceFactory,
        private FilterBuilderFactory $filterBuilderFactory,
        private FilterGroupBuilderFactory $filterGroupBuilderFactory,
        private SortOrderBuilderFactory $sortOrderBuilderFactory,
        private OndemandHelper $ondemandHelper
    ) {
        parent::__construct($context);
    }

    /**
     * @param int|string $orderId
     *
     * @return Payment
     * @throws NoSuchEntityException
     */
    public function getOrderPayment(int|string $orderId): Payment
    {
        return $this->orderPaymentRepository->get($orderId, 'order_id');
    }

    /**
     * @param int|string $paymentId
     *
     * @return Payment
     * @throws NoSuchEntityException
     */
    public function getOrderPaymentByPaymentId(int|string $paymentId): Payment
    {
        return $this->orderPaymentRepository->get($paymentId, 'payment_id');
    }

    /**
     * @param int|string $orderId
     *
     * @return OrderInstallmentPlan
     * @throws NoSuchEntityException
     */
    public function getOrderInstallmentPlan(int|string $orderId): OrderInstallmentPlan
    {
        return $this->orderInstallmentPlanRepository->get($orderId, 'order_id');
    }

    /**
     * @param int|string $orderId
     *
     * @return Payment|null
     */
    public function getOrderLastPayment(int|string $orderId): ?Payment
    {
        $orderPayments = $this->getOrderPayments($orderId);

        return array_shift($orderPayments);
    }

    /**
     * @param int|string $orderId
     *
     * @return array|Payment[]
     */
    public function getOrderPayments(int|string $orderId): array
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

        return $result->getItems();
    }

    /**
     * Generate field filter for repository search
     *
     * @param string $field
     * @param mixed  $value
     * @param string $type
     *
     * @return FilterGroup
     */
    private function getFieldFilter(string $field, mixed $value, string $type = 'eq'): FilterGroup
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
    public function getPaymentErrorMessage(IVerifiableAPIResource $resource): string
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
    public function canUpdatePayment(Order $order): bool
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
            OneyWithoutFees::METHOD_CODE => [
                Order::STATE_PAYMENT_REVIEW
            ],
            Bancontact::METHOD_CODE => [
                Order::STATE_PAYMENT_REVIEW
            ],
            ApplePay::METHOD_CODE => [
                Order::STATE_PAYMENT_REVIEW
            ],
            Amex::METHOD_CODE => [
                Order::STATE_PAYMENT_REVIEW
            ],
            Satispay::METHOD_CODE => [
                Order::STATE_PAYMENT_REVIEW
            ],
            Ideal::METHOD_CODE => [
                Order::STATE_PAYMENT_REVIEW
            ],
            Mybank::METHOD_CODE => [
                Order::STATE_PAYMENT_REVIEW
            ],
        ];

        if (!in_array($order->getState(), $allowedStates[$order->getPayment()->getMethod()])) {
            return false;
        }

        return true;
    }

    /**
     * Check if order's payment can be captured (in case of deferred)
     */
    public function canCaptureOnline(?OrderInterface $order = null, ?CartInterface $quote = null): bool
    {
        $payment = $order?->getPayment() ?? $quote?->getPayment();

        return (bool)$payment?->getAdditionalInformation('is_authorized');
    }

    /**
     * Check if order's payment can be updated
     *
     * @param Order $order
     *
     * @return bool
     */
    public function canSendNewPaymentLink(Order $order): bool
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
     * @param bool $save
     *
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @throws InputException
     */
    public function updateOrderStatus(Order $order, bool $save = true): void
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
                $order->addStatusToHistory($orderStatus, (string)__('Custom Payplug Payments status'));
                if ($save) {
                    $this->orderRepository->save($order);
                }
            }
        }
    }

    /**
     * @param Order $order
     * @param array $data
     *
     * @return Order
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws OrderAlreadyProcessingException
     */
    public function updateOrder(Order $order, array $data = []): Order
    {
        try {
            $orderProcessing = $this->orderProcessingRepository->get($order->getId(), 'order_id');
            $createdAt = new \DateTime($orderProcessing->getCreatedAt());
            if ($createdAt > new \DateTime("now - 1 min")) {
                // Order is currently being processed
                throw new OrderAlreadyProcessingException((string)__('Order is currently being processed.'));
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

            if (!empty($data)) {
              if (!empty($data['status'])) {
                $order->setStatus($data['status']);
              }
            }

            $this->orderRepository->save($order);
            $this->refreshSalesGrid($order->getId());
        } finally {
            $this->orderProcessingRepository->delete($orderProcessing);
        }

        return $order;
    }

  /**
   * @param $order
   * @param $storeId
   * @return ResourcePayment
   */
    public function getPayment($order, $storeId): ResourcePayment
    {
      $orderPayment = $this->getPaymentForOrder($order);

      return $orderPayment->retrieve($storeId);
    }

    /**
     * Abort PayPlug payment if payment has failed
     *
     * @param Order $order
     */
    public function checkPaymentFailureAndAbortPayment(Order $order): void
    {
        try {
            if ($order->getPayment() === false) {
                return;
            }

            $code = $order->getPayment()->getMethod();
            if ($code !== Standard::METHOD_CODE &&
                $code !== InstallmentPlan::METHOD_CODE
            ) {
                return;
            }
            if ($order->getState() !== Order::STATE_PAYMENT_REVIEW) {
                return;
            }

            $storeId = $order->getStoreId();
            $orderPayment = $this->getPaymentForOrder($order);
            if ($orderPayment === null) {
                return;
            }
            $payplugPayment = $orderPayment->retrieve($storeId);
            if ($payplugPayment->failure &&
                $payplugPayment->failure->code &&
                strtolower($payplugPayment->failure->code ?? '') !== 'timeout'
            ) {
                $orderPayment->abort($storeId);
            }
        } catch (HttpException $e) {
            $this->_logger->error('Could not abort payment', [
                'exception' => $e,
                'order' => $order->getId(),
                'message' => $e->getErrorObject()['message'] ?? 'Payplug request error',
            ]);
        } catch (\Exception $e) {
            $this->_logger->error('Could not abort payment', [
                'exception' => $e,
                'order' => $order->getId(),
            ]);
        }
    }

    /**
     * @param Order $order
     *
     * @return Payment|null
     * @throws NoSuchEntityException
     */
    public function getPaymentForOrder(Order $order): ?Payment
    {
        $storeId = $order->getStoreId();
        if ($order->getPayment()->getMethod() === InstallmentPlan::METHOD_CODE) {
            $orderInstallmentPlan = $this->getOrderInstallmentPlan($order->getIncrementId());
            $installmentPlan = $orderInstallmentPlan->retrieve($storeId);
            foreach ($installmentPlan->schedule as $schedule) {
                if (!empty($schedule->payment_ids) && is_array($schedule->payment_ids)) {
                    $paymentId = $schedule->payment_ids[0];
                    if (empty($paymentId)) {
                        continue;
                    }

                    try {
                        return $this->getOrderPaymentByPaymentId($paymentId);
                    } catch (NoSuchEntityException $e) {
                        $orderPayment = $this->orderPaymentRepository->create();
                        $orderPayment->setPaymentId($paymentId);
                        $orderPayment->setOrderId($order->getId());
                        $orderPayment->setIsSandbox($orderInstallmentPlan->isSandbox());

                        return $orderPayment;
                    }
                }
            }
        } else {
            return $this->getOrderPayment($order->getIncrementId());
        }

        return null;
    }

    /**
     * Check if order can be cancelled
     *
     * @param Order $order
     *
     * @return bool
     */
    public function canForceOrderCancel(Order $order): bool
    {
        $method = $order->getPayment()->getMethod();
        if (!$this->isCodePayplugPayment($method) ||
            ($method !== Standard::METHOD_CODE &&
                $method !== InstallmentPlan::METHOD_CODE &&
                $method !== Ondemand::METHOD_CODE
            )
        ) {
            return false;
        }

        if (!$order->isPaymentReview()) {
            return false;
        }

        return true;
    }

    /**
     * Force order cancellation (abort payment)
     *
     * @param Order $order
     *
     * @throws LocalizedException
     * @throws OrderAlreadyProcessingException
     */
    public function forceOrderCancel(Order $order): void
    {
        if (!$order->canCancel()) {
            return;
        }
        if (!$this->canForceOrderCancel($order)) {
            return;
        }

        if ($this->canAbortInstallmentPlan($order)) {
            // If payment is installment plan, preset transaction as pending
            // To avoid automatic creation of paid invoice when processing order's payment
            $order->getPayment()->setTransactionPending(true);
        }
        $order = $this->updateOrder($order);
        if (!$order->isPaymentReview()) {
            if ($order->getState() !== Order::STATE_CANCELED) {
                // Order is no longer in review and hasn't been canceled
                // It means that the payment was validated
                throw new LocalizedException((string)__('The order has been updated without being canceled ' .
                    'because its payment has been validated.'));
            }

            // Order isnt in review anymore, no need to process further
            return;
        }

        if ($this->cancelOrderPayment($order)) {
            // Now that the payment is cancelled on payplug side
            // Trigger update order so that regular process can cancel order
            $this->updateOrder($order);
        }
    }

    /**
     * @param Order $order
     *
     * @return bool
     *
     * @throws LocalizedException
     */
    public function cancelOrderPayment(Order $order): bool
    {
        try {
            if ($this->canAbortInstallmentPlan($order)) {
                $this->cancelInstallmentPlan($order, true);

                return true;
            }

            $this->cancelStandardPayment($order);

            return true;
        } catch (HttpException $e) {
            $payplugError = $e->getErrorObject()['message'] ?? '';
            if ($payplugError === 'The payment was already aborted.') {
                // Payment is already aborted, keep processing to cancel order
                return true;
            }
            throw new LocalizedException((string)__('An error occurred. Please try again.'));
        }
    }

    /**
     * Cancel installment plan payment
     *
     * @param Order $order
     * @param bool  $cancelPayment
     */
    public function cancelInstallmentPlan(Order $order, bool $cancelPayment = false): void
    {
        $storeId = $order->getStoreId();
        $orderInstallmentPlan = $this->getOrderInstallmentPlan($order->getIncrementId());
        if ($cancelPayment) {
            $installmentPlan = $orderInstallmentPlan->retrieve($storeId);
            foreach ($installmentPlan->schedule as $schedule) {
                if (!empty($schedule->payment_ids) && is_array($schedule->payment_ids)) {
                    $paymentId = $schedule->payment_ids[0];
                    if (empty($paymentId)) {
                        continue;
                    }

                    try {
                        $orderPayment = $this->getOrderPaymentByPaymentId($paymentId);
                        $orderPayment->abort($storeId);
                    } catch (NoSuchEntityException $e) {
                        // Payment was not found - no need to abort it
                    }
                }
            }
        }
        $orderInstallmentPlan->abort($storeId);
        $installmentPlan = $orderInstallmentPlan->retrieve($storeId);
        $this->updateInstallmentPlanStatus($orderInstallmentPlan, $installmentPlan);
    }

    /**
     * @param Order $order
     *
     * @return void
     * @throws NoSuchEntityException
     */
    public function cancelStandardPayment(Order $order): void
    {
        $orderPayment = $this->getOrderPayment($order->getIncrementId());
        $orderPayment->abort($order->getStoreId());
    }

    /**
     * Check if order&payment have been validated
     *
     * @param Order $order
     *
     * @return bool
     */
    public function isOrderValidated(Order $order): bool
    {
        if ($order->getState() == Order::STATE_PROCESSING || $order->getState() == Order::STATE_COMPLETE) {
            return true;
        }

        // If Oney payment is still being reviewed, order is validated but still in Payment Review state
        if (($order->getPayment()->getMethod() == Oney::METHOD_CODE ||
            $order->getPayment()->getMethod() == OneyWithoutFees::METHOD_CODE) &&
            $order->getState() == Order::STATE_PAYMENT_REVIEW
        ) {
            return true;
        }

        if ($order->getState() == Order::STATE_CANCELED) {
            return false;
        }

        if (!$this->isPaymentFailure($order)) {
            return true;
        }

        return false;
    }

    /**
     * Send OnDemand payment link
     *
     * @param Order $order
     * @param array $paymentLinkData
     *
     * @return Order
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws OrderAlreadyProcessingException
     * @throws PaymentException
     */
    public function sendNewPaymentLink(Order $order, array $paymentLinkData): Order
    {
        try {
            $orderProcessing = $this->orderProcessingRepository->get($order->getId(), 'order_id');
            $createdAt = new \DateTime($orderProcessing->getCreatedAt());
            if ($createdAt > new \DateTime("now - 1 min")) {
                // Order is currently being processed
                throw new OrderAlreadyProcessingException((string)__('Order is currently being processed.'));
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
    private function createOrderProcessing(Order $order): Processing
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
    public function canAbortInstallmentPlan(Order $order): bool
    {
        if ($order->getPayment() === false) {
            return false;
        }

        if ($order->getPayment()->getMethod() != InstallmentPlan::METHOD_CODE) {
            return false;
        }

        return true;
    }

    /**
     * InstallmentPlan statuses
     *
     * @return array
     */
    public function getInstallmentPlanStatusesLabel(): array
    {
        return [
            OrderInstallmentPlan::STATUS_NEW => 'New',
            OrderInstallmentPlan::STATUS_ONGOING => 'Ongoing',
            OrderInstallmentPlan::STATUS_ABORTED => 'Aborted',
            OrderInstallmentPlan::STATUS_COMPLETE => 'Complete',
        ];
    }

    /**
     * Check if payment is a PayPlug payment
     *
     * @param string $code
     *
     * @return bool
     */
    public function isCodePayplugPayment(string $code): bool
    {
        return in_array($code, [
            Standard::METHOD_CODE,
            InstallmentPlan::METHOD_CODE,
            Ondemand::METHOD_CODE,
            Oney::METHOD_CODE,
            OneyWithoutFees::METHOD_CODE,
            Bancontact::METHOD_CODE,
            ApplePay::METHOD_CODE,
            Amex::METHOD_CODE,
            Satispay::METHOD_CODE,
            Ideal::METHOD_CODE,
            Mybank::METHOD_CODE,
        ]);
    }

    /**
     * Check if payment is a PayPlug payment with PPRO
     *
     * @param string $code
     *
     * @return bool
     */
    public function isCodePayplugPaymentPpro(string $code): bool
    {
        return in_array($code, [
            Satispay::METHOD_CODE,
            Ideal::METHOD_CODE,
            Mybank::METHOD_CODE,
        ]);
    }

    /**
     * @param OrderInstallmentPlan $orderInstallmentPlan
     * @param ResourceInstallmentPlan $installmentPlan
     *
     * @return void
     */
    public function updateInstallmentPlanStatus(OrderInstallmentPlan $orderInstallmentPlan, ResourceInstallmentPlan $installmentPlan): void
    {
        $status = OrderInstallmentPlan::STATUS_NEW;
        if ($installmentPlan->is_active && !$installmentPlan->is_fully_paid) {
            $status = OrderInstallmentPlan::STATUS_ONGOING;
        } elseif (!$installmentPlan->is_active) {
            $status = OrderInstallmentPlan::STATUS_COMPLETE;
            if (!$installmentPlan->is_fully_paid) {
                $status = OrderInstallmentPlan::STATUS_ABORTED;
            }
        }

        $orderInstallmentPlan->setStatus($status);
        $this->orderInstallmentPlanRepository->save($orderInstallmentPlan);
    }

    /**
     * Refresh admin order grid
     *
     * @param int|string $orderId
     *
     * @return void
     */
    public function refreshSalesGrid(int|string $orderId): void
    {
        $this->salesGrid->refresh($orderId);
    }

    /**
     * @param Order $order
     *
     * @return void
     * @throws LocalizedException
     */
    private function updateOrderPayment(Order $order): void
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

        if ($payment->getIsTransactionApproved() ||
            ($isInstallmentPlan && !$payment->getTransactionPending() && !$payment->getIsTransactionDenied())
        ) {
            if (count($order->getInvoiceCollection()) === 0) {
                $invoice = $order->prepareInvoice();
                $invoice->register();
                $order->addRelatedObject($invoice);
                $invoice->setTransactionId($transactionId);

                if (!$isInstallmentPlan) {
                    $order->setState(Order::STATE_PROCESSING);
                    $invoice->pay();
                    $payment->setBaseAmountPaidOnline($order->getBaseGrandTotal());
                    $message = __('Registered update about approved payment.') . ' '
                        . __('Transaction ID: "%1"', $transactionId);
                    $order->addStatusToHistory(
                        $order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING),
                        (string)$message
                    );
                } else {
                    // Order amounts and status history are already handled
                    // In \Payplug\Payments\Gateway\Response\InstallmentPlan\FetchTransactionInformationHandler::handle
                    $invoice->setState(Order\Invoice::STATE_PAID);
                }
            }
        } elseif ($payment->getIsTransactionDenied()) {
            // fetchTransactionInfo has already checked payplug payment status, no need to do it again
            $this->cancelOrderAndInvoice($order, false);
        }
    }

    /**
     * Check if order's payment has failed
     *
     * @param Order $order
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    private function isPaymentFailure(Order $order): bool
    {
        if ($order->getPayment()->getMethod() == InstallmentPlan::METHOD_CODE) {
            $payment = $this->getOrderInstallmentPlan($order->getIncrementId());
        } else {
            $payment = $this->getOrderPayment($order->getIncrementId());
        }
        /** @var Payment|OrderInstallmentPlan $payplugPayment */
        $payplugPayment = $payment->retrieve($order->getStoreId());

        if ($payplugPayment->failure) {
            return true;
        }

        return false;
    }

    /**
     * @param Order $order
     * @param bool $checkPaymentStatus
     *
     * @return void
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function cancelOrderAndInvoice(Order $order, bool $checkPaymentStatus = true): void
    {
        if ($order->getState() != Order::STATE_PAYMENT_REVIEW) {
            return;
        }

        if ($checkPaymentStatus && !$this->isPaymentFailure($order)) {
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
