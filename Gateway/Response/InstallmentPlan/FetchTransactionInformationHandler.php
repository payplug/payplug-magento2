<?php

namespace Payplug\Payments\Gateway\Response\InstallmentPlan;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\MessageQueue\PublisherInterface as MessageQueuePublisherInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\Order\PaymentFactory;
use Payplug\Payments\Model\OrderPaymentRepository;
use Payplug\Payments\Service\CreateOrderInvoice;

class FetchTransactionInformationHandler implements HandlerInterface
{
    public function __construct(
        private readonly SubjectReader $subjectReader,
        private readonly Logger $payplugLogger,
        private readonly PaymentFactory $payplugPaymentFactory,
        private readonly OrderSender $orderSender,
        private readonly OrderPaymentRepository $orderPaymentRepository,
        private readonly Data $payplugHelper,
        private readonly MessageQueuePublisherInterface $messageQueuePublisher
    ) {
    }

    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = $this->subjectReader->readPayment($handlingSubject);

        if ($paymentDO->getPayment() instanceof Payment) {
            /** @var Payment $payment */
            $payment = $paymentDO->getPayment();
            $order = $payment->getOrder();

            $payplugInstallmentPlan = $response['installment_plan'];
            $orderInstallmentPlan = $response['order_installment_plan'];

            $this->payplugHelper->updateInstallmentPlanStatus($orderInstallmentPlan, $payplugInstallmentPlan);

            if ($payplugInstallmentPlan->failure) {
                $payment->setIsTransactionDenied(true);

                return;
            }

            $schedules = $payplugInstallmentPlan->schedule;
            foreach ($schedules as $schedule) {
                if (!empty($schedule->payment_ids) && is_array($schedule->payment_ids)) {
                    $paymentId = $schedule->payment_ids[0];

                    try {
                        $orderPayment = $this->orderPaymentRepository->get($paymentId, 'payment_id');
                    } catch (NoSuchEntityException $e) {
                        /** @var \Payplug\Payments\Model\Order\Payment $orderPayment */
                        $orderPayment = $this->payplugPaymentFactory->create();
                        $orderPayment->setOrderId($order->getIncrementId());
                        $orderPayment->setPaymentId($paymentId);
                        $orderPayment->setIsSandbox(!$payplugInstallmentPlan->is_live);
                    }
                    $payplugPayment = $orderPayment->retrieve($orderPayment->getScopeId($order), $orderPayment->getScope($order));

                    if ($payplugPayment->is_paid && !$orderPayment->isInstallmentPlanPaymentProcessed()) {
                        $this->sendOrderEmail($order);
                        $this->updateInvoiceState($order, $payment);

                        $payment->setTransactionId($payplugInstallmentPlan->id);
                        $payment->setTransactionPending(false);
                        $payment->setIsFraudDetected(false);

                        $amount = $schedule->amount / 100;

                        $totalPaid = $order->getTotalPaid() + $amount;
                        $baseTotalPaid = $order->getBaseTotalPaid() + $amount;

                        $order->setTotalPaid($totalPaid);
                        $order->setBaseTotalPaid($baseTotalPaid);
                        if ($this->payplugHelper->isOrderPending($order)) {
                            $order->setState(Order::STATE_PROCESSING);
                        }

                        $this->messageQueuePublisher->publish(CreateOrderInvoice::MESSAGE_QUEUE_TOPIC, (int)$order->getId());
                        $this->payplugLogger->info(
                            sprintf(
                                '%s: "%s" message published for order %s.',
                                __METHOD__,
                                CreateOrderInvoice::MESSAGE_QUEUE_TOPIC,
                                $order->getId()
                            )
                        );

                        $payment->setBaseShippingCaptured($order->getBaseShippingInclTax());
                        $payment->setShippingCaptured($order->getShippingInclTax());
                        $payment->setBaseAmountPaid($baseTotalPaid);
                        $payment->setBaseAmountPaidOnline($baseTotalPaid);
                        $payment->setAmountPaid($totalPaid);
                        $orderPayment->setIsInstallmentPlanPaymentProcessed(true);
                    }

                    $this->orderPaymentRepository->save($orderPayment);
                }
            }
            if ($payplugInstallmentPlan->is_fully_paid) {
                // Avoid totalDue 0.01 issue
                // When installment plan is fully paid, set total paid to order's total
                $order->setTotalPaid($order->getGrandTotal());
                $order->setBaseTotalPaid($order->getBaseGrandTotal());
            }
        }
    }

    /**
     * Send order email
     *
     * @param Order $order
     */
    private function sendOrderEmail($order)
    {
        if (!$order->getEmailSent()) {
            try {
                $this->orderSender->send($order);
            } catch (\Exception $e) {
                $this->payplugLogger->critical($e);
            }
        }
    }

    /**
     * Flag invoice as paid
     *
     * @param Order   $order
     * @param Payment $payment
     */
    private function updateInvoiceState($order, $payment)
    {
        // Get order invoice
        foreach ($order->getInvoiceCollection() as $invoice) {
            if ($invoice->getTransactionId() == $payment->getTransactionId()) {
                $invoice->load($invoice->getId());
                // to make sure all data will properly load (maybe not required)
            }
        }

        if (isset($invoice) && $invoice instanceof Invoice) {
            // Force Invoice to Paid to avoid fraud detection check
            $invoice->setState(\Magento\Sales\Model\Order\Invoice::STATE_PAID);
            $invoice->save();
        }
    }
}
