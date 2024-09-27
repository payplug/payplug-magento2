<?php

declare(strict_types=1);

namespace Payplug\Payments\Plugin\Adminhtml;

use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Operations\ProcessInvoiceOperation;
use Magento\Sales\Model\OrderRepository;
use Payplug\Payments\Gateway\Config\Standard;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\OrderPaymentRepository;

class CaptureStandardDeferredPayment
{
    public function __construct(
        protected Data $data,
        protected Logger $logger,
        protected ManagerInterface $eventManager,
        protected MessageManagerInterface $messageManager,
        protected RedirectFactory $redirectFactory,
        protected QuoteRepository $quoteRepository,
        protected OrderPaymentRepository $orderPaymentRepository,
        protected OrderRepository $orderRepository
    ) {
    }

    public function aroundExecute(ProcessInvoiceOperation $subject, callable $proceed, ...$args): OrderPaymentInterface
    {
        /** @var OrderPaymentInterface $magentoPayment */
        $magentoPayment = $args[0];
        /** @var InvoiceInterface $invoice */
        $invoice = $args[1];
        $order = $this->orderRepository->get($magentoPayment->getOrder()->getId());
        $quote = $this->quoteRepository->get($order->getQuoteId());
        $quotePayment = $quote->getPayment();
        if ($magentoPayment->getMethod() !== Standard::METHOD_CODE
            || !$this->data->canCaptureOnline(null, $quote)) {
            return $proceed(...$args);
        }

        $this->eventManager->dispatch(
            'sales_order_payment_capture',
            ['payment' => $magentoPayment, 'invoice' => $invoice]
        );

        if ($invoice->getIsPaid()) {
            throw new LocalizedException(
                __('The transaction "%1" cannot be captured yet.', $invoice->getTransactionId())
            );
        }

        $payplugPaymentId = $magentoPayment->getAdditionalInformation('payplug_payment_id');

        if ($payplugPaymentId) {
            try {
                $payplugPayment = $this->orderPaymentRepository->get($payplugPaymentId, 'payment_id');
                $paymentCapture = $payplugPayment->retrieve();
                $paymentObject = $paymentCapture->capture();
                if ($paymentObject) {
                    $magentoPayment->setBaseAmountPaidOnline((float)$quotePayment->getAdditionalInformation('authorized_amount') / 100);
                    $magentoPayment->setLastTransId($payplugPaymentId);
                    $magentoPayment->setAdditionalInformation('is_paid', true);
                    $magentoPayment->setAdditionalInformation('was_deferred', true);
                    $invoice->setIsPaid(true);
                    $invoice->setTransactionId($payplugPaymentId);

                    $order->addCommentToStatusHistory(sprintf('Payment of %s %s successfully captured and paid on Payplug at %s.',
                        (int)($paymentObject->amount) / 100,
                        $paymentObject->currency,
                        date('Y-m-d H:i:s', $paymentObject->paid_at),
                    ), Order::STATE_PROCESSING);

                    $this->orderRepository->save($order);
                }
            } catch (\Exception $e) {
                $invoice->setIsPaid(false);
                $this->logger->info($e->getMessage());
                //If the connection fail when trying to capture the order, then we do not want the invoice to be created.
                throw new \Exception($e->getMessage());
            }
        }

        return $magentoPayment;
    }
}
