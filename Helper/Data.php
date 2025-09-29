<?php

declare(strict_types=1);

namespace Payplug\Payments\Helper;

use DateMalformedStringException;
use DateTime;
use Exception;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilderFactory;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\Search\FilterGroupBuilderFactory;
use Magento\Framework\Api\SearchCriteriaInterfaceFactory;
use Magento\Framework\Api\SortOrderBuilderFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\MessageQueue\PublisherInterface as MessageQueuePublisherInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
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
use Payplug\Payments\Helper\Config as PayplugConfig;
use Payplug\Payments\Helper\Ondemand as OndemandHelper;
use Payplug\Payments\Logger\Logger as PayplugLogger;
use Payplug\Payments\Model\Order\InstallmentPlan as OrderInstallmentPlan;
use Payplug\Payments\Model\Order\Payment;
use Payplug\Payments\Model\Order\Processing;
use Payplug\Payments\Model\Order\ProcessingFactory as PayplugOrderProcessingFactory;
use Payplug\Payments\Model\OrderInstallmentPlanRepository;
use Payplug\Payments\Model\OrderPaymentRepository;
use Payplug\Payments\Model\OrderProcessingRepository;
use Payplug\Payments\Service\CreateOrderInvoice;
use Payplug\Resource\InstallmentPlan as ResourceInstallmentPlan;
use Payplug\Resource\Payment as ResourcePayment;

class Data
{
    public function __construct(
        private readonly OrderPaymentRepository $orderPaymentRepository,
        private readonly OrderRepository $orderRepository,
        private readonly PayplugOrderProcessingFactory $orderProcessingFactory,
        private readonly OrderProcessingRepository $orderProcessingRepository,
        private readonly OrderInstallmentPlanRepository $orderInstallmentPlanRepository,
        private readonly GridInterface $salesGrid,
        private readonly SearchCriteriaInterfaceFactory $searchCriteriaInterfaceFactory,
        private readonly FilterBuilderFactory $filterBuilderFactory,
        private readonly FilterGroupBuilderFactory $filterGroupBuilderFactory,
        private readonly SortOrderBuilderFactory $sortOrderBuilderFactory,
        private readonly OndemandHelper $ondemandHelper,
        private readonly PayplugConfig $payplugConfig,
        private readonly MessageQueuePublisherInterface $messageQueuePublisher,
        private readonly PayplugLogger $payplugLogger,
        private readonly CartRepositoryInterface $cartRepository
    ) {
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getOrderPayment(int|string $orderId): Payment
    {
        return $this->orderPaymentRepository->get($orderId, 'order_id');
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getOrderPaymentByPaymentId(int|string $paymentId): Payment
    {
        return $this->orderPaymentRepository->get($paymentId, 'payment_id');
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getOrderInstallmentPlan(int|string $orderId): OrderInstallmentPlan
    {
        return $this->orderInstallmentPlanRepository->get($orderId, 'order_id');
    }

    public function getOrderLastPayment(int|string $orderId): ?Payment
    {
        $orderPayments = $this->getOrderPayments($orderId);

        return array_shift($orderPayments);
    }

    /**
     * @return Payment[]
     */
    public function getOrderPayments(int|string $orderId): array
    {
        $criteria = $this->searchCriteriaInterfaceFactory->create();

        $filterGroups = [
            $this->getFieldFilter('order_id', $orderId),
        ];

        $criteria->setFilterGroups($filterGroups);

        $sortBuilder = $this->sortOrderBuilderFactory->create();
        $sortBuilder->setField('entity_id');
        $sortBuilder->setDescendingDirection();

        $sortOrder = $sortBuilder->create();

        $criteria->setSortOrders([$sortOrder]);

        $result = $this->orderPaymentRepository->getList($criteria);

        return $result->getItems();
    }

    private function getFieldFilter(string $field, mixed $value, string $type = 'eq'): FilterGroup
    {
        /** @var Filter $filter */
        $filterBuilder = $this->filterBuilderFactory->create();
        $filterBuilder->setField($field);
        $filterBuilder->setConditionType($type);
        $filterBuilder->setValue($value);
        $filter = $filterBuilder->create();

        /** @var FilterGroup $filterGroup */
        $filterGroupBuilder = $this->filterGroupBuilderFactory->create();
        $filterGroupBuilder->addFilter($filter);

        /** @var FilterGroup $filterGroup */
        $filterGroup = $filterGroupBuilder->create();

        return $filterGroup;
    }

    public function canUpdatePayment(OrderInterface $order): bool
    {
        if ($order->getPayment() === false) {
            return false;
        }

        if (!$this->isCodePayplugPayment($order->getPayment()->getMethod())) {
            return false;
        }

        $allowedStates = [
            Standard::METHOD_CODE => [
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_PAYMENT_REVIEW
            ],
            InstallmentPlan::METHOD_CODE => [
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_PAYMENT_REVIEW,
                Order::STATE_PROCESSING,
                Order::STATE_COMPLETE
            ],
            Ondemand::METHOD_CODE => [
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_PAYMENT_REVIEW
            ],
            Oney::METHOD_CODE => [
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_PAYMENT_REVIEW
            ],
            OneyWithoutFees::METHOD_CODE => [
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_PAYMENT_REVIEW
            ],
            Bancontact::METHOD_CODE => [
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_PAYMENT_REVIEW
            ],
            ApplePay::METHOD_CODE => [
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_PAYMENT_REVIEW
            ],
            Amex::METHOD_CODE => [
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_PAYMENT_REVIEW
            ],
            Satispay::METHOD_CODE => [
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_PAYMENT_REVIEW
            ],
            Ideal::METHOD_CODE => [
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_PAYMENT_REVIEW
            ],
            Mybank::METHOD_CODE => [
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_PAYMENT_REVIEW
            ],
        ];

        if (!in_array($order->getState(), $allowedStates[$order->getPayment()->getMethod()])) {
            return false;
        }

        return true;
    }

    public function canCaptureOnline(?OrderInterface $order = null, ?CartInterface $quote = null): bool
    {
        $payment = $order?->getPayment() ?? $quote?->getPayment();

        return (bool)$payment?->getAdditionalInformation('is_authorized');
    }

    public function canSendNewPaymentLink(OrderInterface $order): bool
    {
        if ($order->getPayment() === false) {
            return false;
        }

        if ($order->getPayment()->getMethod() != Ondemand::METHOD_CODE) {
            return false;
        }

        if (!$this->isOrderPending($order)) {
            return false;
        }

        return true;
    }

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @throws InputException
     */
    public function updateOrderStatus(OrderInterface $order, bool $save = true): void
    {
        $this->payplugLogger->info(
            sprintf(
                '%s: Updating Order: %s. Status: %s / State: %s.',
                __METHOD__,
                $order->getId(),
                $order->getStatus(),
                $order->getState()
            )
        );
        $field = null;

        if ($this->isOrderPending($order)) {
            try {
                $payplugOrderPayment = $this->getOrderPayment($order->getIncrementId());
                $payplugPayment = $payplugOrderPayment->retrieve(
                    $payplugOrderPayment->getScopeId($order),
                    $payplugOrderPayment->getScope($order)
                );
                if ($payplugPayment->is_paid && empty($payplugPayment->failure)) {
                    $this->payplugLogger->info(
                        sprintf(
                            '%s: Updating Order: %s state to Processing.',
                            __METHOD__,
                            $order->getId()
                        )
                    );
                    $order->setState(Order::STATE_PROCESSING);
                } elseif (!empty($payplugPayment->authorization->authorized_at) && empty($payment->failure)) {
                    $this->payplugLogger->info(
                        sprintf(
                            '%s: Updating Order: %s state to Payment Review.',
                            __METHOD__,
                            $order->getId()
                        )
                    );

                    $order->setStatus(Order::STATE_PAYMENT_REVIEW);
                    $order->setState(Order::STATE_PROCESSING);
                    $order->addCommentToStatusHistory(__('Transaction authorized but not yet captured.'))
                        ->setIsCustomerNotified(false);

                    $order->getPayment()->setRew(true);
                }
            } catch (Exception $e) {
                $this->payplugLogger->info($e->getMessage());
            }
        }

        if ($order->getState() == Order::STATE_PROCESSING && $order->getStatus() !== Order::STATE_PAYMENT_REVIEW) {
            $field = 'processing_order_status';
        } elseif ($order->getState() == Order::STATE_CANCELED) {
            $field = 'canceled_order_status';
        }
        $this->payplugLogger->info(
            sprintf(
                '%s: Updating Order: %s status to %s.',
                __METHOD__,
                $order->getId(),
                $order->getState()
            )
        );
        if ($field !== null) {
            $orderStatus = $order->getPayment()->getMethodInstance()->getConfigData($field, $order->getStoreId());
            if (!empty($orderStatus) && $orderStatus !== $order->getStatus()) {
                $this->payplugLogger->info(
                    sprintf(
                        '%s: Adding Order: %s status %s to history.',
                        __METHOD__,
                        $order->getId(),
                        $orderStatus
                    )
                );
                $order->addStatusToHistory($orderStatus, (string)__('Custom Payplug Payments status'));
                if ($save) {
                    $this->orderRepository->save($order);
                    $this->payplugLogger->info(
                        sprintf('%s: Order %s saved with new status.', __METHOD__, $order->getId())
                    );
                }
            }
        }
    }

    /**
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws OrderAlreadyProcessingException
     * @throws DateMalformedStringException
     */
    public function updateOrder(OrderInterface $order, array $data = []): OrderInterface
    {
        $this->payplugLogger->info(sprintf('%s: Updating Order %s.', __METHOD__, $order->getId()));
        try {
            $orderProcessing = $this->orderProcessingRepository->get($order->getId(), 'order_id');
            $createdAt = new DateTime($orderProcessing->getCreatedAt());
            if ($createdAt > new DateTime("now - 1 min")) {
                // Order is currently being processed
                throw new OrderAlreadyProcessingException((string)__('Order is currently being processed.'));
            }
            // Order has been set as processing for more than a minute
            // Delete and recreate a new flag
            $this->orderProcessingRepository->delete($orderProcessing);
        } catch (NoSuchEntityException) {
            // Order is not currently being processed
            // Create a new flag to block a concurrent process
        }

        try {
            $orderProcessing = $this->createOrderProcessing($order);
        } catch (Exception $e) {
            $this->payplugLogger->info($e->getMessage());
            return $order;
        }

        try {
            $order = $this->orderRepository->get($order->getId());
            if (!$this->canUpdatePayment($order)) {
                $this->orderProcessingRepository->delete($orderProcessing);
                return $order;
            }

            $this->updateOrderPayment($order);
            $this->updateOrderStatus($order);

            if (!empty($data)) {
                if (!empty($data['status'])) {
                    $order->setStatus($data['status']);
                    $this->payplugLogger->info(
                        sprintf(
                            '%s: Forcing status %s on the order %s.',
                            __METHOD__,
                            $data['status'],
                            $order->getId()
                        )
                    );
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
     * @throws NoSuchEntityException
     */
    public function getPayment(OrderInterface $order): ResourcePayment
    {
        $orderPayment = $this->getPaymentForOrder($order);

        return $orderPayment->retrieve($orderPayment->getScopeId($order), $orderPayment->getScope($order));
    }

    public function checkPaymentFailureAndAbortPayment(OrderInterface $order): void
    {
        try {
            if ($order->getPayment() === false) {
                return;
            }

            $code = $order->getPayment()->getMethod();
            if ($code !== Standard::METHOD_CODE
                && $code !== InstallmentPlan::METHOD_CODE
            ) {
                return;
            }
            if ($this->isOrderPending($order)) {
                return;
            }

            $storeId = $order->getStoreId();
            $orderPayment = $this->getPaymentForOrder($order);
            if ($orderPayment === null) {
                return;
            }
            $payplugPayment = $orderPayment->retrieve($orderPayment->getScopeId($order), $orderPayment->getScope($order));
            if ($payplugPayment->failure
                && $payplugPayment->failure->code
                && strtolower($payplugPayment->failure->code ?? '') !== 'timeout'
            ) {
                $orderPayment->abort((int)$storeId);
            }
        } catch (HttpException $e) {
            $this->payplugLogger->error('Could not abort payment', [
                'exception' => $e,
                'order' => $order->getId(),
                'message' => $e->getErrorObject()['message'] ?? 'Payplug request error',
            ]);
        } catch (Exception $e) {
            $this->payplugLogger->error('Could not abort payment', [
                'exception' => $e,
                'order' => $order->getId(),
            ]);
        }
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getPaymentForOrder(OrderInterface $order): ?Payment
    {
        $storeId = $order->getStoreId();
        if ($order->getPayment()->getMethod() === InstallmentPlan::METHOD_CODE) {
            $orderInstallmentPlan = $this->getOrderInstallmentPlan($order->getIncrementId());
            $installmentPlan = $orderInstallmentPlan->retrieve($orderInstallmentPlan->getScopeId($order), $orderInstallmentPlan->getScope($order));
            foreach ($installmentPlan->schedule as $schedule) {
                if (!empty($schedule->payment_ids) && is_array($schedule->payment_ids)) {
                    $paymentId = $schedule->payment_ids[0];
                    if (empty($paymentId)) {
                        continue;
                    }

                    try {
                        return $this->getOrderPaymentByPaymentId($paymentId);
                    } catch (NoSuchEntityException) {
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

    public function canForceOrderCancel(OrderInterface $order): bool
    {
        $method = $order->getPayment()->getMethod();
        if (!$this->isCodePayplugPayment($method)
            || ($method !== Standard::METHOD_CODE
            && $method !== InstallmentPlan::METHOD_CODE
            && $method !== Ondemand::METHOD_CODE)
        ) {
            return false;
        }

        if (!$order->isPaymentReview()) {
            return false;
        }

        return true;
    }

    /**
     * @throws LocalizedException
     * @throws OrderAlreadyProcessingException
     * @throws DateMalformedStringException
     */
    public function forceOrderCancel(OrderInterface $order): void
    {
        if (!$order->canCancel()) {
            return;
        }
        if (!$this->canForceOrderCancel($order)) {
            return;
        }

        if ($this->canAbortInstallmentPlan($order)) {
            // If payment is an installment plan, preset transaction as pending
            // To avoid automatic creation of paid invoice when processing order's payment
            $order->getPayment()->setTransactionPending(true);
        }
        $order = $this->updateOrder($order);
        if (!$order->isPaymentReview()) {
            if ($order->getState() !== Order::STATE_CANCELED) {
                // Order is no longer in review and hasn't been canceled
                // It means that the payment was validated
                throw new LocalizedException(
                    __('The order has been updated without being canceled because its payment has been validated.')
                );
            }

            // Order isn't in review anymore, no need to process further
            return;
        }

        if ($this->cancelOrderPayment($order)) {
            // Now that the payment is canceled on payplug side
            // Trigger update order so that a regular process can cancel order
            $this->updateOrder($order);
        }
    }

    /**
     * @throws LocalizedException
     */
    public function cancelOrderPayment(OrderInterface $order): bool
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
     * @throws NoSuchEntityException
     */
    public function cancelInstallmentPlan(OrderInterface $order, bool $cancelPayment = false): void
    {
        $storeId = $order->getStoreId();
        $orderInstallmentPlan = $this->getOrderInstallmentPlan($order->getIncrementId());
        if ($cancelPayment) {
            $installmentPlan = $orderInstallmentPlan->retrieve($orderInstallmentPlan->getScopeId($order), $orderInstallmentPlan->getScope($order));
            foreach ($installmentPlan->schedule as $schedule) {
                if (!empty($schedule->payment_ids) && is_array($schedule->payment_ids)) {
                    $paymentId = $schedule->payment_ids[0];
                    if (empty($paymentId)) {
                        continue;
                    }

                    try {
                        $orderPayment = $this->getOrderPaymentByPaymentId($paymentId);
                        $orderPayment->abort((int)$storeId);
                    } catch (NoSuchEntityException) {
                        // Payment was not found - no need to abort it
                    }
                }
            }
        }
        $orderInstallmentPlan->abort((int)$storeId);
        $installmentPlan = $orderInstallmentPlan->retrieve($orderInstallmentPlan->getScopeId($order), $orderInstallmentPlan->getScope($order));
        $this->updateInstallmentPlanStatus($orderInstallmentPlan, $installmentPlan);
    }

    /**
     * @throws NoSuchEntityException
     */
    public function cancelStandardPayment(OrderInterface $order): void
    {
        $orderPayment = $this->getOrderPayment($order->getIncrementId());
        $orderPayment->abort((int)$order->getStoreId());
    }

    /**
     * @throws NoSuchEntityException
     */
    public function isOrderValidated(OrderInterface $order): bool
    {
        if ($order->getState() == Order::STATE_PROCESSING || $order->getState() == Order::STATE_COMPLETE) {
            return true;
        }

        // If Oney payment is still being reviewed, order is validated but still in Payment Review state
        if (($order->getPayment()->getMethod() == Oney::METHOD_CODE
            || $order->getPayment()->getMethod() == OneyWithoutFees::METHOD_CODE)
            && $this->isOrderPending($order)
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
     * @throws AlreadyExistsException
     * @throws DateMalformedStringException
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws OrderAlreadyProcessingException
     * @throws PaymentException
     */
    public function sendNewPaymentLink(OrderInterface $order, array $paymentLinkData): OrderInterface
    {
        try {
            $orderProcessing = $this->orderProcessingRepository->get($order->getId(), 'order_id');
            $createdAt = new DateTime($orderProcessing->getCreatedAt());
            if ($createdAt > new DateTime("now - 1 min")) {
                // Order is currently being processed
                throw new OrderAlreadyProcessingException((string)__('Order is currently being processed.'));
            }
            // Order has been set as processing for more than a minute
            // Delete and recreate a new flag
            $this->orderProcessingRepository->delete($orderProcessing);
        } catch (NoSuchEntityException) {
            // Order is not currently being processed
            // Create a new flag to block concurrent process
        }

        try {
            $orderProcessing = $this->createOrderProcessing($order);
        } catch (Exception $e) {
            $this->payplugLogger->info($e->getMessage());
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

    private function createOrderProcessing(OrderInterface $order): Processing
    {
        $orderProcessing = $this->orderProcessingFactory->create();
        $orderProcessing->setOrderId($order->getId());
        $date = new DateTime();
        $orderProcessing->setCreatedAt($date->format('Y-m-d H:i:s'));
        $this->orderProcessingRepository->save($orderProcessing);

        return $orderProcessing;
    }

    public function canAbortInstallmentPlan(OrderInterface $order): bool
    {
        if ($order->getPayment() === false) {
            return false;
        }

        if ($order->getPayment()->getMethod() != InstallmentPlan::METHOD_CODE) {
            return false;
        }

        return true;
    }

    public function getInstallmentPlanStatusesLabel(): array
    {
        return [
            OrderInstallmentPlan::STATUS_NEW => 'New',
            OrderInstallmentPlan::STATUS_ONGOING => 'Ongoing',
            OrderInstallmentPlan::STATUS_ABORTED => 'Aborted',
            OrderInstallmentPlan::STATUS_COMPLETE => 'Complete',
        ];
    }

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

    public function isCodePayplugPaymentPpro(string $code): bool
    {
        return in_array($code, [
            Satispay::METHOD_CODE,
            Ideal::METHOD_CODE,
            Mybank::METHOD_CODE,
        ]);
    }

    public function isCodePayplugPaymentWithRedirect(string $code): bool
    {
        $isCodePayplugPayment = $this->isCodePayplugPayment($code);

        if (!$isCodePayplugPayment) {
            return false;
        }

        if ($code === ApplePay::METHOD_CODE) {
            return false;
        }

        if ($code === Standard::METHOD_CODE
            && ($this->payplugConfig->isEmbedded() === true || $this->payplugConfig->isIntegrated() === true)
        ) {
            return false;
        }

        return true;
    }

    public function updateInstallmentPlanStatus(
        OrderInstallmentPlan $orderInstallmentPlan,
        ResourceInstallmentPlan $installmentPlan
    ): void {
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

    public function refreshSalesGrid(int|string $orderId): void
    {
        $this->salesGrid->refresh($orderId);
    }

    /**
     * @throws LocalizedException
     */
    private function updateOrderPayment(OrderInterface $order): void
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

        if ($payment->getIsTransactionApproved()
            || ($isInstallmentPlan && !$payment->getTransactionPending() && !$payment->getIsTransactionDenied())
        ) {
            $this->messageQueuePublisher->publish(CreateOrderInvoice::MESSAGE_QUEUE_TOPIC, (int)$order->getId());

            $this->payplugLogger->info(
                sprintf(
                    '%s: "%s" message published for order %s.',
                    __METHOD__,
                    CreateOrderInvoice::MESSAGE_QUEUE_TOPIC,
                    $order->getId()
                )
            );
        } elseif ($payment->getIsTransactionDenied()) {
            // fetchTransactionInfo has already checked payplug payment status, no need to do it again
            $this->cancelOrderAndInvoice($order, false);
        }
    }

    /**
     * @throws NoSuchEntityException
     */
    private function isPaymentFailure(OrderInterface $order): bool
    {
        if ($order->getPayment()->getMethod() == InstallmentPlan::METHOD_CODE) {
            $payment = $this->getOrderInstallmentPlan($order->getIncrementId());
        } else {
            $payment = $this->getOrderPayment($order->getIncrementId());
        }
        /** @var Payment|OrderInstallmentPlan $payplugPayment */
        $payplugPayment = $payment->retrieve($payment->getScopeId($order), $payment->getScope($order));

        if ($payplugPayment->failure) {
            return true;
        }

        return false;
    }

    /**
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function cancelOrderAndInvoice(OrderInterface $order, bool $checkPaymentStatus = true): void
    {
        if (!$this->isOrderPending($order)) {
            return;
        }

        if ($checkPaymentStatus && !$this->isPaymentFailure($order)) {
            return;
        }

        // Manually execute Payment::cancelInvoiceAndRegisterCancellation, which is protected
        $orderInvoice = null;
        foreach ($order->getInvoiceCollection() as $invoice) {
            if ($invoice->getState() == Invoice::STATE_OPEN
                && $invoice->load($invoice->getId())
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

    public function isOrderPending(OrderInterface $order): bool
    {
        return in_array($order->getState(), [Order::STATE_PENDING_PAYMENT, Order::STATE_PAYMENT_REVIEW]);
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getStandardDeferredQuote(ResourcePayment $payment): ?CartInterface
    {
        if (!$payment->metadata['ID Quote']) {
            return null;
        }

        $quote = $this->cartRepository->get($payment->metadata['ID Quote']);
        $quotePayment = $quote->getPayment();

        if ($quotePayment->getMethod() === Standard::METHOD_CODE && $this->payplugConfig->isStandardPaymentModeDeferred()) {
            return $quote;
        }

        return null;
    }

    public function saveAutorizationInformationOnQuote(CartInterface $quote, ResourceInstallmentPlan|ResourcePayment $payment): void
    {
        $quotePayment = $quote->getPayment();
        $quotePayment->setAdditionalInformation('is_authorized', true);
        $quotePayment->setAdditionalInformation('authorized_amount', $payment->authorization->authorized_amount);
        $quotePayment->setAdditionalInformation('authorized_at', $payment->authorization->authorized_at);
        $quotePayment->setAdditionalInformation('expires_at', $payment->authorization->expires_at);
        $quotePayment->setAdditionalInformation('payplug_payment_id', $payment->id);
        $this->cartRepository->save($quote);

        $this->payplugLogger->info('Autorisation information saved on quote');
    }
}
