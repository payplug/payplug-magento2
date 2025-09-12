<?php

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Exception;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Payment\Transaction\ManagerInterface as TransactionManagerInterface;
use Payplug\Payments\Logger\Logger as PayplugLogger;
use Payplug\Payments\Model\Data\RefundRequest;

class CreateOrderRefund
{
    public const MESSAGE_QUEUE_TOPIC = 'payplug.order.refunding';

    public function __construct(
        private readonly TransactionManagerInterface $transactionManager,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PayplugLogger $payplugLogger
    ) {
    }

    public function execute(RefundRequest $refundRequest): void
    {
        $order = $refundRequest->getOrder();
        $refundId = $refundRequest->getRefundId();
        $payment = $order->getPayment();

        $isTransactionExists = $this->transactionManager->isTransactionExists($refundId, $payment->getId(), $order->getId());

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
