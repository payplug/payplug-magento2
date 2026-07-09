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
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Payment;
use Payplug\Exception\PayplugServerException;
use Payplug\Payments\Gateway\Config\Standard;
use Payplug\Payments\Helper\Config as PayplugConfig;
use Payplug\Payments\Helper\Data as PayplugDataHelper;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\Order\Payment as PayplugOrderPayment;
use Payplug\Payments\Model\OrderPaymentRepository;
use Payplug\Payments\Service\HostedFieldsIpnLock;
use Throwable;

class CreateHostedFieldsPaymentAfterQuoteSubmit implements ObserverInterface
{
    /**
     * @param CommandPoolInterface $commandPool
     * @param PaymentDataObjectFactoryInterface $paymentDataObjectFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderPaymentRepository $orderPaymentRepository
     * @param PayplugConfig $payplugConfig
     * @param HostedFieldsIpnLock $hostedFieldsIpnLock
     * @param Logger $logger
     * @param PayplugDataHelper $payplugDataHelper
     */
    public function __construct(
        private readonly CommandPoolInterface $commandPool,
        private readonly PaymentDataObjectFactoryInterface $paymentDataObjectFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderPaymentRepository $orderPaymentRepository,
        private readonly PayplugConfig $payplugConfig,
        private readonly HostedFieldsIpnLock $hostedFieldsIpnLock,
        private readonly Logger $logger,
        private readonly PayplugDataHelper $payplugDataHelper,
    ) {
    }

    /**
     * Execute the Hosted Fields gateway payment action
     *
     * @param Observer $observer
     * @return void
     * @throws Throwable
     */
    public function execute(Observer $observer): void
    {
        /** @var OrderInterface $order */
        $order = $observer->getEvent()->getData('order');
        $payment = $order->getPayment();
        $websiteId = (int) $order->getStore()->getWebsiteId();

        if ($payment instanceof Payment === false
            || $payment->getMethod() !== Standard::METHOD_CODE
            || $this->payplugConfig->isHostedFieldsActive($websiteId) === false
        ) {
            return;
        }

        // Hold the lock from before the API call (which triggers the IPN) until the order payment
        // row is committed, so a fast incoming IPN waits for it instead of failing to find the row.
        $orderIncrementId = (string) $order->getIncrementId();
        $lockAcquired = $this->hostedFieldsIpnLock->acquire($orderIncrementId);

        try {
            $gatewayCommand = $payment->getMethodInstance()->getConfigPaymentAction();

            if ($gatewayCommand === MethodInterface::ACTION_AUTHORIZE_CAPTURE) {
                $gatewayCommand = 'capture';
            }

            $this->commandPool->get($gatewayCommand)->execute([
                'payment' => $this->paymentDataObjectFactory->create($payment),
                'amount' => $order->getGrandTotal(),
                'is_quote_submited' => true
            ]);

            $payplugOrderPayment = $this->orderPaymentRepository->get(
                $order->getIncrementId(),
                PayplugOrderPayment::ORDER_ID
            );

            $transactionType = match ($gatewayCommand) {
                MethodInterface::ACTION_AUTHORIZE  => TransactionInterface::TYPE_AUTH,
                MethodInterface::ACTION_AUTHORIZE_CAPTURE => TransactionInterface::TYPE_CAPTURE,
                default => TransactionInterface::TYPE_ORDER,
            };

            $payment->setTransactionId($payplugOrderPayment->getPaymentId());
            $payment->addTransaction($transactionType);

            $this->orderRepository->save($order);
        } /** @noinspection PhpRedundantCatchClauseInspection */ catch (PayplugServerException $e) {
            $this->logger->error(sprintf(
                'PayPlug Standard gateway payment creation failed for order %s. Reason : %s',
                $order->getIncrementId(),
                $e->__toString()
            ));
            $this->payplugDataHelper->cancelOrderAndInvoice($order, false);

            throw $e;
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                'PayPlug Standard gateway payment creation failed for order %s. Reason : %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));

            $this->payplugDataHelper->cancelOrderAndInvoice($order, false);

            throw $e;
        } finally {
            if ($lockAcquired) {
                $this->hostedFieldsIpnLock->release($orderIncrementId);
            }
        }
    }
}
