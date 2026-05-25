<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Payplug\Payments\Gateway\Config\PayplugPayment as PayplugPaymentConfig;
use Payplug\Payments\Gateway\Config\Standard;
use Payplug\Payments\Helper\Config as PayplugConfig;
use Payplug\Payments\Logger\Logger;
use Throwable;

class CreateHostedFieldsPaymentAfterQuoteSubmit implements ObserverInterface
{
    /**
     * @param CommandPoolInterface $commandPool
     * @param PaymentDataObjectFactoryInterface $paymentDataObjectFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param PayplugConfig $payplugConfig
     * @param PayplugPaymentConfig $payplugPaymentConfig
     * @param Logger $logger
     */
    public function __construct(
        private readonly CommandPoolInterface $commandPool,
        private readonly PaymentDataObjectFactoryInterface $paymentDataObjectFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PayplugConfig $payplugConfig,
        private readonly PayplugPaymentConfig $payplugPaymentConfig,
        private readonly Logger $logger
    ) {
    }

    /**
     * Execute the Hosted Fields gateway payment action
     *
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        /** @var OrderInterface $order */
        $order = $observer->getEvent()->getData('order');
        $payment = $order->getPayment();

        if ($payment instanceof Payment === false
            || $payment->getMethod() !== Standard::METHOD_CODE
            || $this->payplugConfig->isHostedFieldsActive() === false
        ) {
            return;
        }

        try {
            $gatewayCommand = $payment->getMethodInstance()->getConfigPaymentAction();

            $this->commandPool->get($gatewayCommand)->execute([
                'payment' => $this->paymentDataObjectFactory->create($payment),
                'amount' => $order->getGrandTotal(),
                'force_hosted_fields_payment' => true
            ]);

            $this->orderRepository->save($order);
        } catch (Throwable $e) {
            $this->logger->critical(sprintf(
                'PayPlug Standard gateway payment creation failed for order %s: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ), ['exception' => $e]);

            $this->cancelOrderQuietly($order);

            throw new LocalizedException(__('An error occurred while processing your payment. Please try again.'));
        }
    }

    /**
     * Cancel the saved order silently
     *
     * @param OrderInterface $order
     * @return void
     */
    private function cancelOrderQuietly(OrderInterface $order): void
    {
        if ($order instanceof Order === false) {
            return;
        }

        try {
            $order->cancel();
            $this->orderRepository->save($order);
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                'Failed to cancel order %s after gateway payment failure: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));
        }
    }
}
