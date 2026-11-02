<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Phrase\RendererInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Payplug\Payments\Gateway\Config\InstallmentPlan;
use Payplug\Payments\Logger\Logger as PayplugLogger;
use Throwable;

class CreateOrderInvoice
{
    public const MESSAGE_QUEUE_TOPIC = 'payplug.order.invoicing';

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param PayplugLogger $payplugLogger
     * @param AppEmulation $appEmulation
     * @param InvoiceSender $invoiceSender
     * @param RendererInterface $phraseRenderer
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PayplugLogger $payplugLogger,
        private readonly AppEmulation $appEmulation,
        private readonly InvoiceSender $invoiceSender,
        private readonly RendererInterface $phraseRenderer
    ) {
    }

    /**
     * Create invoice for order
     *
     * @param int $orderId
     * @return void
     */
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

        // Before Magento 2.4.7, app emulation does not set the phrase renderer, so the status history
        // comment and the invoice email built from a queue consumer are not translated.
        // @see https://github.com/magento/magento2/commit/6f72057cc4cbcde0154e724e3113d7146d568328
        $initialPhraseRenderer = Phrase::getRenderer();

        if ($order->getPayment()->getMethod() == InstallmentPlan::METHOD_CODE) {
            // Order amounts and status history are already handled
            // In \Payplug\Payments\Gateway\Response\InstallmentPlan\FetchTransactionInformationHandler::handle
            $invoice->setState(Invoice::STATE_PAID);
        } else {
            $invoice->pay();

            $payment->setBaseAmountPaidOnline($order->getBaseGrandTotal());

            $order->setState(Order::STATE_PROCESSING);

            $defaultStatus = $order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING);

            $storeId = (int) $order->getStoreId();
            $this->appEmulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
            Phrase::setRenderer($this->phraseRenderer);
            $comment = __('Invoice is created') . '. ' . __('Transaction ID: "%1"', $transactionId);
            Phrase::setRenderer($initialPhraseRenderer);
            $this->appEmulation->stopEnvironmentEmulation();

            $order->addStatusToHistory($defaultStatus, $comment);

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
            Phrase::setRenderer($this->phraseRenderer);
            $this->invoiceSender->send($invoice);
        } catch (Throwable $e) {
            $this->payplugLogger->error($e->getMessage());
        } finally {
            Phrase::setRenderer($initialPhraseRenderer);
        }
    }
}
