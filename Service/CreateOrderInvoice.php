<?php

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Payplug\Payments\Gateway\Config\InstallmentPlan;
use Payplug\Payments\Logger\Logger as PayplugLogger;

class CreateOrderInvoice
{
    public const MESSAGE_QUEUE_TOPIC = 'payplug.order.invoicing';

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PayplugLogger $payplugLogger
    ) {
    }

    public function execute(OrderInterface $order): void
    {
        if ($order->getInvoiceCollection()->count() > 0) {
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
                __('Registered update about approved payment.') . ' ' . __('Transaction ID: "%1"', $transactionId)
            );
        }

        $order->addRelatedObject($invoice);

        $this->orderRepository->save($order);
    }
}
