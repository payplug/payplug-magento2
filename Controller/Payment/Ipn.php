<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Controller\Payment;

use Exception;
use Laminas\Http\Response;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\MessageQueue\PublisherInterface as MessageQueuePublisherInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Payplug\Exception\ConfigurationException;
use Payplug\Exception\ConfigurationNotSetException;
use Payplug\Exception\PayplugException;
use Payplug\Notification;
use Payplug\Payments\Exception\OrderAlreadyProcessingException;
use Payplug\Payments\Gateway\Response\Standard\FetchTransactionInformationHandler;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\Data\RefundRequestFactory;
use Payplug\Payments\Model\Order\Payment;
use Payplug\Payments\Model\OrderPaymentRepository;
use Payplug\Payments\Service\BuildHostedFieldsParamsHash;
use Payplug\Payments\Service\CreateOrderRefund;
use Payplug\Payments\Service\HostedFieldsIpnLock;
use Payplug\Resource\InstallmentPlan;
use Payplug\Resource\IVerifiableAPIResource;
use Payplug\Resource\Payment as PaymentResource;
use Payplug\Resource\Refund;
use Payplug\Responses\HostedFieldTransactionResource;

class Ipn extends AbstractPayment
{
    private const HOSTED_FIELDS_SUCCESS_CONTENT_RETURN = 'OK';

    /**
     * @param Config $payplugConfig
     * @param FetchTransactionInformationHandler $fetchTransactionInformationHandler
     * @param PaymentReturn $paymentReturn
     * @param MessageQueuePublisherInterface $messageQueuePublisher
     * @param RefundRequestFactory $refundRequestFactory
     * @param OrderPaymentRepository $orderPaymentRepository
     * @param BuildHostedFieldsParamsHash $buildHostedFieldsParamsHash
     * @param HostedFieldsIpnLock $hostedFieldsIpnLock
     * @param Context $context
     * @param Session $checkoutSession
     * @param OrderFactory $salesOrderFactory
     * @param Logger $logger
     * @param Data $payplugHelper
     * @throws LocalizedException
     */
    public function __construct(
        private readonly Config $payplugConfig,
        private readonly FetchTransactionInformationHandler $fetchTransactionInformationHandler,
        private readonly PaymentReturn $paymentReturn,
        private readonly MessageQueuePublisherInterface $messageQueuePublisher,
        private readonly RefundRequestFactory $refundRequestFactory,
        private readonly OrderPaymentRepository $orderPaymentRepository,
        private readonly BuildHostedFieldsParamsHash $buildHostedFieldsParamsHash,
        private readonly HostedFieldsIpnLock $hostedFieldsIpnLock,
        Context $context,
        Session $checkoutSession,
        OrderFactory $salesOrderFactory,
        Logger $logger,
        Data $payplugHelper
    ) {
        parent::__construct($context, $checkoutSession, $salesOrderFactory, $logger, $payplugHelper);

        $formKey = $this->_objectManager->get(FormKey::class);
        $this->getRequest()->setParam('form_key', $formKey->getFormKey());
    }

    /**
     * Action called when IPN is received
     *
     * Can update order status when payment or refund notification is received
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $this->logger->info('--- Starting IPN Action ---');

        /** @var Raw $response */
        $response = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $response->setContents('');

        try {
            /** @var Http $request */
            $request = $this->getRequest();
            $body = $request->getContent();

            $this->logger->info($request->getUriString());

            $ipnStoreId = $this->getRequest()->getParam('ipn_store_id');
            $ipnSandbox = $this->getRequest()->getParam('ipn_sandbox');

            $this->payplugConfig->setPayplugApiKey((int)$ipnStoreId, (bool) $ipnSandbox);
            $this->logger->info('Key submited');

            $hostedFieldsIdentifier = $request->getParam('IDENTIFIER');

            if ($hostedFieldsIdentifier) {
                $resource = $this->treatHostedFieldsRequest();
            } else {
                $resource = Notification::treat($body);
            }

            if ($resource instanceof PaymentResource) {
                $this->processPayment($response, $resource);

                if ($hostedFieldsIdentifier) {
                    $response->setContents(self::HOSTED_FIELDS_SUCCESS_CONTENT_RETURN);
                }

                return $response;
            }

            if ($resource instanceof InstallmentPlan) {
                $this->processInstallmentPlan($response, $resource);

                return $response;
            }

            if ($resource instanceof Refund) {
                $this->processRefund($response, $resource);

                if ($hostedFieldsIdentifier) {
                    $response->setContents(self::HOSTED_FIELDS_SUCCESS_CONTENT_RETURN);
                }

                return $response;
            }
        } catch (PayplugException $e) {
            $this->logger->error($e->__toString());

            /** @var Json $response */
            $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $response->setHttpResponseCode(500);
            $response->setData(['exception' => $e->getMessage()]);

            return $response;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            $response->setHttpResponseCode(500);

            return $response;
        }

        return $response;
    }

    /**
     * Treat a request made by the hosted fields IPN
     *
     * @return IVerifiableAPIResource
     * @throws ConfigurationException
     * @throws ConfigurationNotSetException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws Exception
     */
    private function treatHostedFieldsRequest(): IVerifiableAPIResource
    {
        $request = $this->getRequest();
        $transactionId = $request->getParam('TRANSACTIONID');
        $operationType = $request->getParam('OPERATIONTYPE');
        $orderIncrementId = $request->getParam('ORDERID');

        if (empty($transactionId) || empty($operationType) || empty($orderIncrementId)) {
            throw new LocalizedException(__('Transaction ID or Operation Type or Order ID is missing'));
        }

        /** Secure payment/auth reading via hash comparison */
        if (in_array($operationType, ['payment', 'authorization'])) {
            $websiteId = (int) $this->getRequest()->getParam('website_id');

            if (empty($websiteId)) {
                throw new LocalizedException(__('Website ID is missing'));
            }

            /**
             * Sanitize ipn callback parameters. Assuming no extra params added.
             * Url copied/pasted from backend (config) to Dalenys console.
             */
            $ipnParams = $request->getParams();
            unset($ipnParams['form_key'], $ipnParams['HASH'], $ipnParams['website_id']);

            $hashFromRequest = (string) $request->getParam('HASH');
            $calculatedHash = $this->buildHostedFieldsParamsHash->execute(
                $ipnParams,
                BuildHostedFieldsParamsHash::SEPARATOR_ACCOUNT_KEY,
                $websiteId
            );

            if (hash_equals($calculatedHash, $hashFromRequest) === false) {
                throw new LocalizedException(__('Provided hash does not match calculated one'));
            }

            // The IPN can reach us before the checkout request has committed the order payment row.
            // Wait for the checkout request to release the lock (row committed) before processing.
            $this->hostedFieldsIpnLock->waitForRelease((string) $request->getParam('ORDERID'));

            $hostedFieldsResourceObject = new HostedFieldTransactionResource(['DATA' => [$ipnParams]]);
            $hostedFieldsResource = get_object_vars($hostedFieldsResourceObject);

            return PaymentResource::fromAttributes($hostedFieldsResource);
        }

        /**
         * Secure capture/refund call reading via API fetch
         * API fetch is required to get aggregated captures with initial payment object
         * IPN provide only single object. Assuming capture here.
         */
        if (in_array($operationType, ['capture', 'refund'])) {
            $payplugOrderPayment = $this->orderPaymentRepository->get($orderIncrementId, Payment::ORDER_ID);

            return $payplugOrderPayment->retrieve();
        }

        throw new LocalizedException(__('Cannot handle operation type "%1"', $operationType));
    }

    /**
     * Process ipn payment call
     *
     * @param Raw $response
     * @param PaymentResource $paymentResource
     * @return void
     * @throws LocalizedException
     */
    private function processPayment(Raw $response, PaymentResource $paymentResource): void
    {
        $this->logger->info('This is a payment call.');
        $this->logger->info('Payment ID : ' . $paymentResource->id);

        $orderIncrementId = $paymentResource->metadata['Order'] ?? $paymentResource->metadata['order_id'] ?? null;

        $order = $this->salesOrderFactory->create();
        $order->loadByIncrementId($orderIncrementId);

        if ($order->getId() === null) {
            $this->logger->info('Order not found for ' . $orderIncrementId);
            throw new LocalizedException(__('Order not found for %1', $orderIncrementId));
        }

        try {
            $this->payplugHelper->getOrderInstallmentPlan((string)$order->getIncrementId());
            $response->setStatusHeader(
                Response::STATUS_CODE_200,
                null,
                "200 payment for installment plan not processed"
            );

            return;
        } catch (NoSuchEntityException) {
            $this->logger->info('Processing payment IPN for orders not linked to an installment plan');
        }

        if (!$paymentResource->is_paid) {
            // If we are actually reviewing a standard deferred payment not yet captured
            $this->payplugHelper->saveAutorizationInformation($order, $paymentResource);

            $this->logger->info('Transaction was not paid.');
        }

        /**
         * If this is a standard deferred payment captured, we try saving the customer card after the Capture IPN
         * (Only doable after payment)
         */
        $orderPayment = $order->getPayment();

        if ($orderPayment === null) {
            $this->logger->info('Order Payment not found for Order IncrementID ' . $orderIncrementId);
            return;
        }

        $isAuthorizeOnly = $this->paymentReturn->isAuthorizedOnlyStandardPaymentFromMethod($orderPayment->getMethod());
        $isHostedFields = (bool) ($paymentResource->is_hosted_fields ?? false);

        $customerId = $order->getCustomerId();

        if ($isHostedFields) {
            /**
             * A "paid" Hosted Fields payment transaction is considered valid and contains a card hash
             * An "authorized" Hosted Fields transaction is considered valid and contains a card hash
             */
            $canSaveCard = $paymentResource->is_paid || $isAuthorizeOnly;
        } else {
            /**
             * An "authorized" and "paid" Integrated Payment transaction is considered valid and contains a card hash
             */
            $canSaveCard = $isAuthorizeOnly && $paymentResource->is_paid;
        }

        if ($customerId !== null && $canSaveCard === true) {
            /**
             * Save customer card for all standard payment modes
             */
            $this->fetchTransactionInformationHandler->saveCustomerCard(
                $paymentResource,
                (int) $customerId,
                (int) $order->getStoreId()
            );
        }

        $anomymizedPaymentResource = clone $paymentResource;
        $anomymizedPaymentResource->__set('billing', null);
        $anomymizedPaymentResource->__set('shipping', null);
        $anomymizedPaymentResource->__set('card', null);

        $this->logger->info('Gathering payment details...', [
            'details' => var_export($anomymizedPaymentResource, true),
        ]);
        $this->logger->info('Order state current: ' . $order->getState());

        $this->processOrder($response, $order);
    }

    /**
     * Handle payment update
     *
     * @param Raw $response
     * @param Order $order
     * @return void
     */
    private function processOrder(Raw $response, Order $order): void
    {
        if ($this->payplugHelper->canUpdatePayment($order)) {
            try {
                $this->payplugHelper->checkPaymentFailureAndAbortPayment($order);
                $this->payplugHelper->updateOrder($order);
                $responseCode = 200;
                $responseDetail = '200 Order updated.';
            } catch (PayplugException $e) {
                $this->logger->error($e->__toString());
                $responseCode = 500;
                $responseDetail = '500 Error while updating order.';
            } catch (OrderAlreadyProcessingException $e) {
                // Order is already being processed (by payment return controller or admin update button)
                // No need to log as it is not an error case
                $responseCode = 200;
                $responseDetail = '200 Order currently being processed.';
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
                $responseCode = 500;
                $responseDetail = '500 Error while updating order.';
            }
        } else {
            $responseCode = 200;
            $responseDetail = '200 IPN already received.';
        }

        $response->setStatusHeader($responseCode, null, $responseDetail);
    }

    /**
     * Process installment plan ipn call
     *
     * @param Raw $response
     * @param InstallmentPlan $resource
     * @return void
     */
    private function processInstallmentPlan(Raw $response, InstallmentPlan $resource): void
    {
        $this->logger->info('This is an installment plan call.');
        $installmentPlan = $resource;
        $this->logger->info('Installment Plan ID : ' . $installmentPlan->id);

        $orderIncrementId = $installmentPlan->metadata['Order'];

        $order = $this->salesOrderFactory->create();
        $order->loadByIncrementId($orderIncrementId);

        try {
            $this->payplugHelper->getOrderInstallmentPlan($order->getIncrementId());
        } catch (NoSuchEntityException) {
            $response->setStatusHeader(
                Response::STATUS_CODE_500,
                null,
                "500 installment plan not found for order [$orderIncrementId]"
            );

            return;
        }

        if ($installmentPlan->failure) {
            $this->logger->info('Transaction was not paid.');
            $this->logger->info('Canceling order');
        }

        $this->logger->info('Gathering installment plan details...', [
            'details' => var_export($installmentPlan, true),
        ]);
        $this->logger->info('Order state current: ' . $order->getState());

        $this->processOrder($response, $order);
    }

    /**
     * Process refund ipn call
     *
     * @param Raw $response
     * @param Refund $resource
     * @return void
     */
    private function processRefund(Raw $response, Refund $resource): void
    {
        $this->logger->info('This is a refund call.');
        $this->logger->info('Refund ID : ' . $resource->id);
        $refund = $resource;

        $paymentId = $refund->payment_id;
        try {
            $orderPayment = $this->payplugHelper->getOrderPaymentByPaymentId($paymentId);
        } catch (NoSuchEntityException $e) {
            $this->logger->info(sprintf('500 Unknown payment %s.', $paymentId));
            $response->setStatusHeader(
                Response::STATUS_CODE_500,
                null,
                sprintf('500 Unknown payment %s.', $paymentId)
            );

            return;
        }

        $orderIncrementId = $orderPayment->getOrderId();
        $order = $this->salesOrderFactory->create();
        $order->loadByIncrementId($orderIncrementId);
        if (!$order->getId()) {
            $this->logger->info(sprintf('500 Unknown order %s.', $orderIncrementId));
            $response->setStatusHeader(
                Response::STATUS_CODE_500,
                null,
                sprintf('500 Unknown order %s.', $orderIncrementId)
            );

            return;
        }

        try {
            $this->payplugHelper->getOrderInstallmentPlan($order->getIncrementId());
            $this->logger->info("200 refund for installment plan not processed");
            $response->setStatusHeader(
                Response::STATUS_CODE_200,
                null,
                "200 refund for installment plan not processed"
            );

            return;
        } catch (NoSuchEntityException) {
            $this->logger->info('Processing refund IPN for orders not linked to an installment plan');
        }

        $refundRequest = $this->refundRequestFactory->create();
        $refundRequest->setOrderId((int)$order->getId());
        $refundRequest->setRefundId($refund->id);
        $refundRequest->setRefundPaymentId($refund->payment_id);
        $refundRequest->setRefundAmount($refund->amount);

        $this->messageQueuePublisher->publish(CreateOrderRefund::MESSAGE_QUEUE_TOPIC, $refundRequest);

        $message = '200 Message submitted in queue for processing';
        $this->logger->info($message);
        $response->setStatusHeader(Response::STATUS_CODE_200, null, $message);
    }
}
