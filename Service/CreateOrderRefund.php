<?php

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Exception;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Payment\Transaction\ManagerInterface as TransactionManagerInterface;
use Payplug\Payments\Logger\Logger as PayplugLogger;
use Payplug\Payments\Model\Data\RefundRequest;
use Throwable;

class CreateOrderRefund
{
    public const MESSAGE_QUEUE_TOPIC = 'payplug.order.refunding';

    /**
     * @param TransactionManagerInterface $transactionManager
     * @param OrderRepositoryInterface $orderRepository
     * @param PayplugLogger $payplugLogger
     */
    public function __construct(
        private readonly TransactionManagerInterface $transactionManager,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PayplugLogger $payplugLogger
    ) {
    }

    /**
     * Create refund for order
     *
     * @param RefundRequest $refundRequest
     * @return void
     */
    public function execute(RefundRequest $refundRequest): void
    {
        $orderId = $refundRequest->getOrderId();

        $this->payplugLogger->info(
            sprintf(
                '%s: "%s" processing refund for order %s.',
                __METHOD__,
                CreateOrderInvoice::MESSAGE_QUEUE_TOPIC,
                $orderId
            )
        );

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (Throwable $e) {
            $this->payplugLogger->error($e->getMessage());
            return;
        }

        $refundId = $refundRequest->getRefundId();
        $payment = $order->getPayment();

        $isTransactionExists = $this->transactionManager->isTransactionExists(
            $refundId,
            $payment->getId(),
            $order->getId()
        );

        if ($isTransactionExists) {
            $this->payplugLogger->info(sprintf('Transaction already exists %s.', $refundId));
            return;
        }

        $refundPaymentId = $refundRequest->getRefundPaymentId();
        $refundAmount = $refundRequest->getRefundAmount();

        try {
            $payment->setTransactionId($refundId);
            $payment->setParentTransactionId($refundPaymentId);

            $amountToRefund = $refundAmount / 100;
            $payment->registerRefundNotification($amountToRefund);

            $this->orderRepository->save($order);
            $this->payplugLogger->info(
                sprintf('Payment refund created successfully for order %s', $order->getIncrementId())
            );
        } catch (Exception $e) {
            $this->payplugLogger->info(sprintf('Error while creating full refund %s.', $e->getMessage()));
        }
    }
}
