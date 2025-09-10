<?php

declare(strict_types=1);

namespace Payplug\Payments\Cron;

use DateTime;
use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Area;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Model\ResourceModel\Quote\Payment\CollectionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceManagementInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Container\OrderIdentity;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Payplug\Payments\Api\Data\OrderInterface as PayplugOrderInterface;
use Payplug\Payments\Gateway\Config\Standard;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Logger\Logger;

class AutoCaptureDeferredPayments
{
    public const FORCED_CAPTURE_DELAY_IN_DAYS = 6;

    public function __construct(
        private readonly TimezoneInterface $timezone,
        private readonly Logger $logger,
        private readonly OrderPaymentRepositoryInterface $paymentRepository,
        private readonly CollectionFactory $quotePaymentCollectionFactory,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly InvoiceManagementInterface $invoiceService,
        private readonly InvoiceSender $invoiceSender,
        private readonly Transaction $transaction,
        private readonly Config $config,
        private readonly TransportBuilder $transportBuilder,
        private readonly OrderIdentity $orderIdentity
    ) {
    }

    /**
     * Loop through all pending deferred payments, and force to capture them
     * if the time elapsed since the order creation exceed 6 days
     */
    public function execute(): void
    {
        $this->logger->info('Running the AutoCaptureDeferredPayments cron');

        // All the invoiceable order will populate this array
        $orderToInvoiceIds = [];

        $currentDay = $this->timezone->date();

        // Get all the order payment that are not paid and using the payplug standard deferred (sales_order_payment table)
        $searchOrderPaymentCriteria = $this->searchCriteriaBuilder
            ->addFilter('method', Standard::METHOD_CODE)
            ->addFilter('base_amount_paid', null, 'null')
            ->addFilter('additional_information', '%is_deferred_payment%', 'like')
            ->addFilter('additional_information', '%fail_to_capture%', 'nlike')
            ->create();
        $ordersPayments = $this->paymentRepository->getList($searchOrderPaymentCriteria)->getItems();

        // We map the quote and order ids together
        $quotePaymentsToOrderPayments = [];
        foreach ($ordersPayments as $orderPayment) {
            $quoteId = $orderPayment->getAdditionalInformation('quote_id');
            /** @noinspection PhpIllegalArrayKeyTypeInspection */
            $quotePaymentsToOrderPayments[$quoteId] = $orderPayment->getParentId();
        }

        // As the authorization date is saved on the quote payment object, we load them (quote_payment table)
        $quotePaymentCollection = $this->quotePaymentCollectionFactory->create();
        $quotePayments = $quotePaymentCollection
            ->addFieldToSelect('*')
            ->addFieldToFilter('quote_id', ['in' => array_keys($quotePaymentsToOrderPayments)])
            ->addFieldToFilter('additional_information', ['like' => '%is_authorized%'])
            ->getItems();

        $deferredPaymentValidationDate = new DateTime();
        // Using the quotePayment additionnal info we filter on the authorization date exceeding or equal to 6 days
        foreach ($quotePayments as $quotePayment) {
            // Unix timestamp
            $authorizedAtTimestamp = $quotePayment->getAdditionalInformation('authorized_at');
            $deferredPaymentValidationDate->setTimestamp($authorizedAtTimestamp);
            $difference = $currentDay->diff($deferredPaymentValidationDate);
            if ($difference->days >= AutoCaptureDeferredPayments::FORCED_CAPTURE_DELAY_IN_DAYS) {
                // We add the order id to the array of the invoiceables ones
                $orderId = $quotePaymentsToOrderPayments[$quotePayment->getQuoteId()];
                $this->logger->info(sprintf(
                    'The order id %s has been validated for %s days and still is not captured, it was flagged as capturable.',
                    $orderId,
                    $difference->days
                ));
                $orderToInvoiceIds[] = $orderId;
            }
        }

        // We get all the invoiceables orders and we capture them
        $searchOrderCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $orderToInvoiceIds, 'in')
            ->create();
        $orders = $this->orderRepository->getList($searchOrderCriteria)->getItems();

        foreach ($orders as $order) {
            $this->createInvoiceAndCapture($order);
        }

        $this->logger->info('The AutoCaptureDeferredPayments cron is over');
    }

    public function createInvoiceAndCapture(OrderInterface $order): void
    {
        $orderId = $order->getId();

        if (!$order->canInvoice()) {
            $this->logger->info(sprintf('The order id %s does not allow an invoice to be create. Not capturing.', $orderId));
            $this->setFailedToCapture($order);

            return;
        }

        $this->logger->info(sprintf('The order id %s is now being invoiced and captured.', $orderId));

        try {
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setRequestedCaptureCase($invoice::CAPTURE_ONLINE);
            $invoice->addComment('Order automatically invoiced and captured after 6 days of authorization.');
            // Throw Environment emulation nesting is not allowed as of 2.4.6 https://github.com/magento/magento2/issues/36134
            $invoice->register();

            $transactionSave = $this->transaction
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            $transactionSave->save();
            $this->invoiceSender->send($invoice);

            $order->addCommentToStatusHistory(
                __('Notified customer about invoice creation #%1.', $invoice->getId())
            )->setIsCustomerNotified(true);

            $this->orderRepository->save($order);

            $websiteOwnerEmail = $this->config->getWebsiteOwnerEmail();
            $this->sendEmail(
                $websiteOwnerEmail,
                'Forced payment capture',
                sprintf('The order id %s have been invoiced and captured.', $orderId),
                (int)$order->getStoreId()
            );
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'Forbidden error')) {
                $history = sprintf('The order entity id %s cannot be retrieved anymore from payplug Api.', $order->getEntityId());
            } else {
                $history = sprintf('An unexpected error occured with order entityId %s - %s', $order->getEntityId(), $e->getMessage());
            }
            $this->logger->info(sprintf('Auto capture cron failed and wont run again for this order. You can try to create the invoice manually. Error message : %s', $history));

            // Get a fresh order without all the invoice collection items associated
            $order = $this->orderRepository->get($orderId);
            $order->addCommentToStatusHistory(
                $history,
            )->setIsCustomerNotified(false);
            $order->setStatus(PayplugOrderInterface::FAILED_CAPTURE);
            $this->orderRepository->save($order);
            $this->setFailedToCapture($order);
        }
    }

    public function setFailedToCapture(OrderInterface $order): void
    {
        $orderPayment = $order->getPayment();
        $orderPayment->setAdditionalInformation('fail_to_capture', true);
        $this->paymentRepository->save($orderPayment);
    }

    /**
     * @throws MailException
     * @throws LocalizedException
     */
    public function sendEmail(string $toMail, string $subject, string $message, int $storeId): void
    {
        $variables['data']['comment'] = $message;
        $this->transportBuilder->setTemplateIdentifier('auto_capture_email_template')
            ->setTemplateOptions(
                [
                    'area' => Area::AREA_FRONTEND,
                    'store' => $storeId,
                    'subject' => $subject,
                ]
            )->setTemplateVars($variables)
            ->setFromByScope($this->orderIdentity->getEmailIdentity())
            ->addTo($toMail)
            ->setReplyTo($toMail)
            ->getTransport()
            ->sendMessage();
    }
}
