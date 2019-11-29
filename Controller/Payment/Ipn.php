<?php

namespace Payplug\Payments\Controller\Payment;

use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Payplug\Exception\PayplugException;
use Payplug\Notification;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Resource\InstallmentPlan;
use Payplug\Resource\Payment;
use Payplug\Resource\Refund;

class Ipn extends AbstractPayment
{
    /**
     * @var Config
     */
    private $payplugConfig;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session       $checkoutSession
     * @param \Magento\Sales\Model\OrderFactory     $salesOrderFactory
     * @param Logger                                $logger
     * @param Data                                  $payplugHelper
     * @param Config                                $payplugConfig
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $salesOrderFactory,
        Logger $logger,
        Data $payplugHelper,
        Config $payplugConfig
    ) {
        parent::__construct($context, $checkoutSession, $salesOrderFactory, $logger, $payplugHelper);

        $this->payplugConfig = $payplugConfig;
        $formKey = $this->_objectManager->get(\Magento\Framework\Data\Form\FormKey::class);
        $this->getRequest()->setParam('form_key', $formKey->getFormKey());
    }

    /**
     * Action called when IPN is received
     * Can update order status when payment or refund notification is received
     */
    public function execute()
    {
        $this->logger->info('--- Starting IPN Action ---');

        /** @var Raw $response */
        $response = $this->resultFactory->create(ResultFactory::TYPE_RAW);

        try {
            /** @var \Magento\Framework\App\Request\Http $this->getRequest() */
            $body = $this->getRequest()->getContent();
            $debug = (int) $this->getRequest()->getParam('debug');

            $ipnStoreId = $this->getRequest()->getParam('ipn_store_id');

            if ($debug == 1) {
                $response = $this->processDebugCall($response);

                return $response;
            }

            $this->logger->info('This is not a debug call.');

            $ipnSandbox = $this->getRequest()->getParam('ipn_sandbox');
            $this->payplugConfig->setPayplugApiKey($ipnStoreId, (bool) $ipnSandbox);
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
     * @param string      $field
     * @param int         $storeId
     * @param string|null $path
     *
     * @return mixed
     */
    private function getConfigValue($field, $storeId, $path = null)
    {
        return $this->payplugConfig->getConfigValue($field, ScopeInterface::SCOPE_STORE, $storeId, $path);
    }

    /**
     * @param Raw $response
     *
     * @return Raw|Json
     */
    private function processDebugCall($response)
    {
        $this->logger->info('This is a debug call.');
        $cid = (int) $this->payplugConfig->getConfigValue('company_id');
        if ((int) $this->getRequest()->getParam('cid') == $cid) {
            $ipnStoreId = $this->getRequest()->getParam('ipn_store_id');
            $environmentMode = $this->getConfigValue('environmentmode', $ipnStoreId);
            $embeddedMode = $this->getConfigValue('payment_page', $ipnStoreId);
            $oneClick = $this->getConfigValue(
                'one_click',
                $ipnStoreId,
                'payment/payplug_payments_standard/'
            );

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
     * @param Raw     $response
     * @param Payment $resource
     */
    private function processPayment($response, $resource)
    {
        $this->logger->info('This is a payment call.');
        $payment = $resource;
        $this->logger->info('Payment ID : ' . $payment->id);

        $orderIncrementId = $payment->metadata['Order'];

        $order = $this->salesOrderFactory->create();
        $order->loadByIncrementId($orderIncrementId);

        try {
            $this->payplugHelper->getOrderInstallmentPlan($order->getIncrementId());
            $response->setStatusHeader(200, null, "200 payment for installment plan not processed");

            return;
        } catch (NoSuchEntityException $e) {
            // We want to process payment IPN for orders not linked to an installment plan
        }

        if (!$payment->is_paid) {
            $this->logger->info('Transaction was not paid.');
            $this->logger->info('Canceling order');
        }

        $this->logger->info('Gathering payment details...');
        $this->logger->info($payment);
        $this->logger->info('Order state current: ' . $order->getState());

        $this->processOrder($response, $order);
    }

    /**
     * @param Raw     $response
     * @param Order   $order
     */
    private function processOrder($response, $order)
    {
        $responseCode = null;
        $responseDetail = null;
        if ($this->payplugHelper->canUpdatePayment($order)) {
            try {
                $this->payplugHelper->updateOrder($order);
                $responseCode = 200;
                $responseDetail = '200 Order updated.';
            } catch (PayplugException $e) {
                $this->logger->error($e->__toString());
                $responseCode = 500;
                $responseDetail = '500 Error while updating order.';
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
     * @param Raw             $response
     * @param InstallmentPlan $resource
     */
    private function processInstallmentPlan($response, $resource)
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

        $this->logger->info('Gathering installment plan details...');
        $this->logger->info($installmentPlan);
        $this->logger->info('Order state current: ' . $order->getState());

        $this->processOrder($response, $order);
    }

    /**
     * @param Raw    $response
     * @param Refund $resource
     */
    private function processRefund($response, $resource)
    {
        $this->logger->info('This is a refund call.');
        $this->logger->info('Refund ID : '.$resource->id);
        $refund = $resource;

        $paymentId = $refund->payment_id;
        try {
            $orderPayment = $this->payplugHelper->getOrderPaymentByPaymentId($paymentId);
        } catch (NoSuchEntityException $e) {
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

        try {
            $this->payplugHelper->getOrderInstallmentPlan($order->getIncrementId());
            $response->setStatusHeader(200, null, "200 refund for installment plan not processed");

            return;
        } catch (NoSuchEntityException $e) {
            // We want to process refund IPN for orders not linked to an installment plan
        }

        try {
            $amountToRefund = $refund->amount / 100;
            $order->getPayment()->registerRefundNotification($amountToRefund);
            $response->setStatusHeader(200, null, '200 Order updated.');
        } catch (\Exception $e) {
            $response->setStatusHeader(
                500,
                null,
                sprintf('500 Error while creating full refund %s.', $e->getMessage())
            );
        }
    }
}
