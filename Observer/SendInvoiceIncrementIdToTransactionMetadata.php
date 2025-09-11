<?php

declare(strict_types=1);

namespace Payplug\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Payplug\Exception\ConfigurationException;
use Payplug\Exception\ConfigurationNotSetException;
use Payplug\Payments\Helper\Data as PayplugDataHelper;
use Payplug\Payments\Logger\Logger as PayplugLogger;

class SendInvoiceIncrementIdToTransactionMetadata implements ObserverInterface
{
    public function __construct(
        private readonly PayplugDataHelper $payplugDataHelper,
        private readonly PayplugLogger $payplugLogger,
        private readonly OrderRepositoryInterface $orderRepository
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var InvoiceInterface $invoice */
        $invoice = $observer->getEvent()->getData('invoice');
        $order = $this->orderRepository->get($invoice->getOrderId());
        $method = $order->getPayment()?->getMethod();

        if ($this->payplugDataHelper->isCodePayplugPayment($method) === false) {
            return;
        }

        try {
            $payplugPayment = $this->payplugDataHelper->getOrderPayment($order->getIncrementId());
        } catch (NoSuchEntityException) {
            $this->payplugLogger->error('Could not retrieve order payment');
            return;
        }

        $resourcePayment = $payplugPayment->retrieve((int)$order->getStoreId());
        $currentMetadata = $resourcePayment->metadata;
        $currentMetadata['Invoice'] = $invoice->getIncrementId();

        try {
            $payplugPayment->update([
                'metadata' => $currentMetadata
            ], (int)$order->getStoreId());
        } catch (ConfigurationNotSetException|ConfigurationException $e) {
            $this->payplugLogger->error($e->getMessage());
        }
    }
}
