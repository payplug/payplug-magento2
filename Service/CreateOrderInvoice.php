<?php

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Payplug\Payments\Gateway\Config\InstallmentPlan;
use Payplug\Payments\Logger\Logger as PayplugLogger;
use Throwable;

class CreateOrderInvoice
{
    public const MESSAGE_QUEUE_TOPIC = 'payplug.order.invoicing';

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PayplugLogger $payplugLogger
    ) {
    }

    public function execute(int $orderId): void
    {
        $this->payplugLogger->info(
            sprintf(
                '%s: "%s" processing invoice for order %s.',
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

        if ($order->getInvoiceCollection()->count() > 0) {
            $this->payplugLogger->info(__(
                '%s: "%s" invoice already created for order %s.',
                __METHOD__,
                CreateOrderInvoice::MESSAGE_QUEUE_TOPIC,
                $order->getId()
            ));

            return;
        }

        $payment = $order->getPayment();
        $transactionId = $payment->getLastTransId();

        $invoice = $order->prepareInvoice();

        try {
            $invoice->register();
        } catch (LocalizedException $e) {
            $this->payplugLogger->error($e->getMessage());
            return;
        }

        $invoice->setTransactionId($transactionId);

        if ($order->getPayment()->getMethod() == InstallmentPlan::METHOD_CODE) {
            // Order amounts and status history are already handled
            // In \Payplug\Payments\Gateway\Response\InstallmentPlan\FetchTransactionInformationHandler::handle
            $invoice->setState(Invoice::STATE_PAID);
        } else {
            $invoice->pay();

            $payment->setBaseAmountPaidOnline($order->getBaseGrandTotal());

            $order->setState(Order::STATE_PROCESSING);
            $order->addStatusToHistory(
                $order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING),
                __('Invoice is created.') . ' ' . __('Transaction ID: "%1"', $transactionId)
            );

            $this->payplugLogger->info(
                sprintf(
                    '%s: "%s" message consumed for order %s. Invoice is created.',
                    __METHOD__,
                    CreateOrderInvoice::MESSAGE_QUEUE_TOPIC,
                    $order->getId()
                )
            );
        }

        $order->addRelatedObject($invoice);

        try {
            $this->orderRepository->save($order);
        } catch (Throwable $e) {
            $this->payplugLogger->error($e->getMessage());
        }
    }
}
