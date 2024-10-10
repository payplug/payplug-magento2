<?php

declare(strict_types=1);

namespace Payplug\Payments\Cron;

use DateInterval;
use DateTime;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Cache\Manager;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Model\ResourceModel\Quote\Payment\CollectionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Payment\Repository;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\StoreManagerInterface;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Gateway\Config\Standard;

class AutoCaptureDeferredPayments
{
    public function __construct(
        private TimezoneInterface $timezone,
        private Logger $logger,
        private Repository $paymentRepository,
        private CollectionFactory $quotePaymentCollectionFactory,
        private SearchCriteriaBuilder $searchCriteriaBuilder,
        private OrderRepository $orderRepository,
        private InvoiceService $invoiceService,
        private InvoiceSender $invoiceSender,
        private Transaction $transaction
    ) {
    }

    /**
     * Loop through all of the pendant deferred paiements, and force to capture them
     * if the time elapsed since the order creation exceed 7 days
     */
    public function execute(): void
    {
        $this->logger->info('Running AutoCaptureDeferredPayments Cron');

        // All the invoiceable order will populate this array
        $orderToInvoiceIds = [];

        $currentDay = $this->timezone->date();

        // Get all the order payment that are not paid and using the payplug standard deferred (sales_order_payment table)
        $searchOrderPaymentCriteria = $this->searchCriteriaBuilder
            ->addFilter('method', Standard::METHOD_CODE)
            ->addFilter('base_amount_paid', null, 'null')
            ->addFilter('additional_information', '%is_deferred_payment%', 'like')
            ->create();
        $ordersPayments = $this->paymentRepository->getList($searchOrderPaymentCriteria)->getItems();

        $this->logger->info(count($ordersPayments));

        // We map the quote and order ids together
        $quoteIds = [];
        $quotePaymentsToOrderPayments = [];
        foreach ($ordersPayments as $orderPayment) {
            $quoteId =  $orderPayment->getAdditionalInformation('quote_id');
            $quoteIds[] = $quoteId;
            $quotePaymentsToOrderPayments[$quoteId] = $orderPayment->getParentId();
        }

        $this->logger->info('Mapping : ');
        $this->logger->info(print_r($quotePaymentsToOrderPayments, true));

        // As the authorization date is saved on the quote payment object, we load them (quote_payment table)
        $quotePaymentCollection = $this->quotePaymentCollectionFactory->create();
        $quotePayments = $quotePaymentCollection
            ->addFieldToSelect('*')
            ->addFieldToFilter('quote_id', ['in' => $quoteIds])
            ->addFieldToFilter('additional_information', ['like' => '%is_authorized%'])
            ->getItems();

        // Using the quotePayment additionnal info we filter on the authorization date exceeding or equal to 7 days
        foreach($quotePayments as $quotePayment) {
            $this->logger->info('PaymentId : ');
            $this->logger->info($quotePayment->getQuoteId());
            //Unix timestamp
            $authorizedAtTimestamp = $quotePayment->getAdditionalInformation('authorized_at');
            $deferredPaymentValidationDate = new DateTime();
            $deferredPaymentValidationDate->setTimestamp($authorizedAtTimestamp);
            $difference = $currentDay->diff($deferredPaymentValidationDate);
            $this->logger->info($difference->days);
            if ($difference->days >= 7) {
                // We add the order id to the array of the invoiceables ones
                $orderId = $quotePaymentsToOrderPayments[$quotePayment->getQuoteId()];
                $this->logger->info(sprintf(
                    'The order id %s has been validated for %s days and still is not captured, it was flagged as capturable.',
                    $orderId, $difference->days
                ));
                $orderToInvoiceIds[] = $orderId;
            }
        }

        $this->logger->info(print_r($orderToInvoiceIds, true));
        // We get all the invoiceables orders and we capture them
        $searchOrderCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $orderToInvoiceIds, 'in')
            ->create();
        $orders = $this->orderRepository->getList($searchOrderCriteria)->getItems();

        $this->logger->info('Orders past 7 days : ');
        $this->logger->info(count($orders));
        foreach($orders as $order) {
            $this->createInvoiceAndCapture($order);
        }
    }

    public function createInvoiceAndCapture(OrderInterface $order): void
    {
        $orderId = $order->getId();

        if (!$orderId) {
            $this->logger->info(sprintf('The order id %s does no longer exist. Not capturing.', $orderId));
            return;
        }

        if (!$order->canInvoice()) {
            $this->logger->info(sprintf('The order id %s does not allow an invoice to be create. Not capturing.', $orderId));
            return;
        }

        $this->logger->info(sprintf('The order id %s is now being invoiced and captured.', $orderId));

        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase($invoice::CAPTURE_ONLINE);
        $invoice->addComment('Order automatically invoiced and captured after 7 days of authorization.');
        //Throw Environment emulation nesting is not allowed as of 2.4.6 https://github.com/magento/magento2/issues/36134
        $invoice->register();
        $invoice->save();

        $transactionSave = $this->transaction
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
        $transactionSave->save();
        $this->invoiceSender->send($invoice);

        $order->addCommentToStatusHistory(
            __('Notified customer about invoice creation #%1.', $invoice->getId())
        )->setIsCustomerNotified(true)->save();
    }
}
