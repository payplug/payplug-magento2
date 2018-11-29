<?php

namespace Payplug\Payments\Controller\Payment;

use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Payplug\Exception\PayplugException;
use Payplug\Notification;
use Payplug\Payments\Model\PaymentMethod;
use Payplug\Payplug;
use Payplug\Resource\Payment;
use Payplug\Resource\Refund;

class Ipn extends AbstractPayment
{
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
            $body = file_get_contents('php://input');
            $debug = (int) $this->getRequest()->getParam('debug');

            $ipnStoreId = $this->getRequest()->getParam('ipn_store_id');

            if ($debug == 1) {
                $this->logger->info('This is a debug call.');

                $cid = (int) $this->payplugHelper->getConfigValue('company_id');
                if ((int) $this->getRequest()->getParam('cid') == $cid) {
                    $environmentMode = $this->payplugHelper->getConfigValue('environmentmode', ScopeInterface::SCOPE_STORE, $ipnStoreId);
                    $embeddedMode = $this->payplugHelper->getConfigValue('payment_page', ScopeInterface::SCOPE_STORE, $ipnStoreId);
                    $oneClick = $this->payplugHelper->getConfigValue('one_click', ScopeInterface::SCOPE_STORE, $ipnStoreId);

                    $data = [
                        'is_module_active' => 1,
                        'sandbox_mode' => (int) ($environmentMode === PaymentMethod::ENVIRONMENT_TEST),
                        'embedded_mode' => (int) ($embeddedMode === PaymentMethod::PAYMENT_PAGE_EMBEDDED),
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

            $this->logger->info('This is not a debug call.');

            $validKey = $this->paymentMethod->setAPIKey();
            if ($validKey != null) {
                Payplug::setSecretKey($validKey);
                $this->logger->info('Key submited');
            }
            $resource = Notification::treat($body);

            if ($resource instanceof Payment) {
                $this->logger->info('This is a payment call.');
                $payment = $resource;
                $this->logger->info('Payment ID : ' . $payment->id);

                $newState = Order::STATE_NEW;
                $pendingState = Order::STATE_PENDING_PAYMENT;
                $processingState = Order::STATE_PROCESSING;

                $orderIncrementId = $payment->metadata['Order'];

                $order = $this->salesOrderFactory->create();
                $order->loadByIncrementId($orderIncrementId);

                $currentState = $order->getState();
                if ($currentState == ''
                    || $currentState == null
                    || $currentState == $newState
                ) {
                    usleep(3000000);
                    $order->loadByIncrementId($orderIncrementId);
                    $currentState = $order->getState();
                }


                if (!$payment->is_paid) {

                    $this->logger->info('Transaction was not paid.');
                    $this->logger->info('Canceling order');

                    $failureMessage = $this->payplugHelper->getPaymentErrorMessage($payment);
                    $this->paymentMethod->cancelOrder($order, true, $failureMessage);

                    $this->logger->info('Order canceled.');

                    $response->setStatusHeader(200, null, '200 Order canceled.');

                    return $response;
                }

                $this->logger->info('Gathering payment details...');
                $this->logger->info($payment);
                $this->logger->info('Order state current: ' . $currentState);

                $responseCode = null;
                $responseDetail = null;
                switch ($currentState) {
                    case $newState:
                    case $pendingState:
                        try {
                            $this->paymentMethod->processOrder($order, $payment->id);
                            if ($this->payplugHelper->getConfigValue('generate_invoice', ScopeInterface::SCOPE_STORE, $order->getStoreId())) {
                                $this->logger->info('Generating invoice');
                            } else {
                                $this->logger->info('Invoice generating is disabled.');
                            }
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
                        break;
                    case $processingState:
                        $responseCode = 200;
                        $responseDetail = '200 IPN already received.';
                        break;
                    default:
                        break;
                }

                if ($responseCode !== null) {
                    $response->setStatusHeader($responseCode, null, $responseDetail);
                }

                return $response;
            }

            if ($resource instanceof Refund) {
                // TODO refund
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
}
