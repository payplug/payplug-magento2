<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Order\Payment\Repository;
use Magento\Sales\Model\OrderRepository;
use Magento\Store\Model\ScopeInterface;
use Payplug\Exception\PayplugException;
use Payplug\Notification;
use Payplug\Payments\Exception\OrderAlreadyProcessingException;
use Payplug\Payments\Gateway\Config\Standard as StandardConfig;
use Payplug\Payments\Gateway\Response\Standard\FetchTransactionInformationHandler;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\OrderPaymentRepository;
use Payplug\Resource\InstallmentPlan;
use Payplug\Resource\Payment;
use Payplug\Resource\Refund;

class Ipn extends AbstractPayment
{
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderFactory $salesOrderFactory,
        Logger $logger,
        Data $payplugHelper,
        private Config $payplugConfig,
        protected OrderPaymentRepository $paymentRepository,
        protected CartRepositoryInterface $cartRepository,
        protected OrderRepositoryInterface $orderRepository,
        protected OrderPaymentRepositoryInterface $magentoPaymentRepository,
        protected FetchTransactionInformationHandler $fetchTransactionInformationHandler
    ) {
        parent::__construct($context, $checkoutSession, $salesOrderFactory, $logger, $payplugHelper);

        $formKey = $this->_objectManager->get(FormKey::class);
        $this->getRequest()->setParam('form_key', $formKey->getFormKey());
    }

    /**
     * Action called when IPN is received
     *
     * Can update order status when payment or refund notification is received
     */
    public function execute(): Redirect|ResultInterface|Json
    {
        $this->logger->info('--- Starting IPN Action ---');

        /** @var Raw $response */
        $response = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $response->setContents('');

        try {
            /** @var Http $this->getRequest() */
            $body = $this->getRequest()->getContent();
            $debug = (int) $this->getRequest()->getParam('debug');

            $ipnStoreId = $this->getRequest()->getParam('ipn_store_id');

            if ($debug == 1) {
                $response = $this->processDebugCall($response);

                return $response;
            }

            $this->logger->info('This is not a debug call.');

            $ipnSandbox = $this->getRequest()->getParam('ipn_sandbox');
            $this->payplugConfig->setPayplugApiKey((int)$ipnStoreId, (bool) $ipnSandbox);
            $this->logger->info('Key submited');

            $resource = Notification::treat($body);

            if ($resource instanceof Payment) {
                $this->processPayment($response, $resource);

                return $response;
            }

            if ($resource instanceof InstallmentPlan) {
                $this->processInstallmentPlan($response, $resource);

                return $response;
            }

            if ($resource instanceof Refund) {
                $this->processRefund($response, $resource);

                return $response;
            }
        } catch (PayplugException $e) {
            $this->logger->error($e->__toString());

            /** @var Json $response */
            $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $response->setHttpResponseCode(500);
            $response->setData(['exception' => $e->getMessage()]);

            return $response;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return $response;
        }

        return $response;
    }

    /**
     * Get configuration value
     */
    private function getConfigValue(string $field, int $storeId, ?string $path = null): mixed
    {
        return $this->payplugConfig->getConfigValue($field, ScopeInterface::SCOPE_STORE, $storeId, $path);
    }

    /**
     * Process debug ipn call
     */
    private function processDebugCall(Raw $response): Raw|Json|ResultInterface
    {
        $this->logger->info('This is a debug call.');
        $cid = (int) $this->payplugConfig->getConfigValue('company_id');
        if ((int) $this->getRequest()->getParam('cid') == $cid) {
            $ipnStoreId = $this->getRequest()->getParam('ipn_store_id');
            $environmentMode = $this->getConfigValue('environmentmode', $ipnStoreId);
            $embeddedMode = $this->getConfigValue('payment_page', $ipnStoreId);
            $oneClick = $this->payplugConfig->isOneClick((int)$ipnStoreId);

            $data = [
                'is_module_active' => 1,
                'sandbox_mode' => (int) ($environmentMode === Config::ENVIRONMENT_TEST),
                'embedded_mode' => (int) ($embeddedMode === Config::PAYMENT_PAGE_EMBEDDED),
                'one_click' => (int) $oneClick,
                'cid' => 1
            ];

            /** @var Json $response */
            $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $response->setHttpResponseCode(200);
            $response->setData($data);

            return $response;
        }

        $this->logger->info('Access not granted.');
        $response->setHttpResponseCode(403);
        $response->setContents('Access not granted.');

        return $response;
    }

    /**
     * Process ipn payment call
     */
    private function processPayment(Raw $response, Payment $resource): void
    {
        $this->logger->info('This is a payment call.');
        $payment = $resource;
        $this->logger->info('Payment ID : ' . $payment->id);

        $orderIncrementId = $payment->metadata['Order'];

        $order = $this->salesOrderFactory->create();
        $order->loadByIncrementId($orderIncrementId);

        try {
            $this->payplugHelper->getOrderInstallmentPlan((string)$order->getIncrementId());
            $response->setStatusHeader(200, null, "200 payment for installment plan not processed");

            return;
        } catch (NoSuchEntityException $e) {
            // We want to process payment IPN for orders not linked to an installment plan
        }

        $standardDeferredQuote = $this->isStandardDeferredPayment($order, $payment);
        if (!$payment->is_paid) {
            // If we are actually reviewing a standard deferred payment not yet captured
            if ($standardDeferredQuote) {
                $quotePayment = $standardDeferredQuote->getPayment();
                // Add the additionnals informations to the deferred Order payment object
                $quotePayment->setAdditionalInformation('is_authorized', true);
                $quotePayment->setAdditionalInformation('authorized_amount', $payment->authorization->authorized_amount);
                $quotePayment->setAdditionalInformation('authorized_at', $payment->authorization->authorized_at);
                $quotePayment->setAdditionalInformation('expires_at', $payment->authorization->expires_at);
                $quotePayment->setAdditionalInformation('payplug_payment_id', $payment->id);
                $this->cartRepository->save($standardDeferredQuote);
                $response->setStatusHeader(200, null, "200 payment for deferred standard payment not processed");

                return;
            }

            $this->logger->info('Transaction was not paid.');
            $this->logger->info('Canceling order');
        }

        // If this is a standard deferred payment captured, we try saving the customer card after the Capture IPN (Only doable after payment)
        if ($standardDeferredQuote) {
            $this->fetchTransactionInformationHandler->saveCustomerCard($payment, $standardDeferredQuote->getCustomerId(), $standardDeferredQuote->getStoreId());
        }

        $this->logger->info('Gathering payment details...', [
            'details' => var_export($payment, true),
        ]);
        $this->logger->info('Order state current: ' . $order->getState());

        $this->processOrder($response, $order);
    }

    private function isStandardDeferredPayment(OrderInterface $order, Payment $payment): ?Quote
    {
        if ($order->getIncrementId() || !$payment->metadata['ID Quote']) {
            return null;
        }

        /** @var Quote $quote */
        $quote = $this->cartRepository->get($payment->metadata['ID Quote']);
        $quotePayment = $quote->getPayment();
        if ($quotePayment->getMethod() === StandardConfig::METHOD_CODE && $this->payplugConfig->isStandardPaymentModeDeferred()) {
            return $quote;
        }

        return null;
    }

    /**
     * Handle payment update
     */
    private function processOrder(Raw $response, Order $order): void
    {
        $responseCode = null;
        $responseDetail = null;
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
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
                $responseCode = 500;
                $responseDetail = '500 Error while updating order.';
            }
        } else {
            $responseCode = 200;
            $responseDetail = '200 IPN already received.';
        }

        if ($responseCode !== null) {
            $response->setStatusHeader($responseCode, null, $responseDetail);
        }
    }

    /**
     * Process installment plan ipn call
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
        } catch (NoSuchEntityException $e) {
            $response->setStatusHeader(500, null, "500 installment plan not found for order [$orderIncrementId]");

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
     */
    private function processRefund(Raw $response, Refund $resource): void
    {
        $this->logger->info('This is a refund call.');
        $this->logger->info('Refund ID : '.$resource->id);
        $refund = $resource;

        $paymentId = $refund->payment_id;
        try {
            $orderPayment = $this->payplugHelper->getOrderPaymentByPaymentId($paymentId);
        } catch (NoSuchEntityException $e) {
            $this->logger->info(sprintf('500 Unknown payment %s.', $paymentId));
            $response->setStatusHeader(
                500,
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
                500,
                null,
                sprintf('500 Unknown order %s.', $orderIncrementId)
            );

            return;
        }

        try {
            $this->payplugHelper->getOrderInstallmentPlan($order->getIncrementId());
            $this->logger->info("200 refund for installment plan not processed");
            $response->setStatusHeader(200, null, "200 refund for installment plan not processed");

            return;
        } catch (NoSuchEntityException $e) {
            // We want to process refund IPN for orders not linked to an installment plan
        }

        try {
            $amountToRefund = $refund->amount / 100;
            $order->getPayment()->registerRefundNotification($amountToRefund);
            $order->save();
            $this->logger->info('200 Order updated.');
            $response->setStatusHeader(200, null, '200 Order updated.');
        } catch (\Exception $e) {
            $this->logger->info(sprintf('500 Error while creating full refund %s.', $e->getMessage()));
            $response->setStatusHeader(
                500,
                null,
                sprintf('500 Error while creating full refund %s.', $e->getMessage())
            );
        }
    }
}
