<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\OrderFactory;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Exception\OrderAlreadyProcessingException;
use Payplug\Payments\Gateway\Config\Standard as StandardConfig;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Resource\Payment;

class PaymentReturn extends AbstractPayment
{
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderFactory $salesOrderFactory,
        Logger $logger,
        Data $payplugHelper,
        protected Config $config,
        protected CartRepositoryInterface $cartRepository
    ) {
        parent::__construct($context, $checkoutSession, $salesOrderFactory, $logger, $payplugHelper);
    }

    /**
     * Handle return from PayPlug payment page
     */
    public function execute(): Redirect|ResultInterface|Json
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        $redirectUrlSuccess = 'checkout/onepage/success';
        $redirectUrlCart = 'checkout/cart';
        try {
            $lastIncrementId = $this->getCheckout()->getLastRealOrderId();
            if (!$lastIncrementId) {
                $this->logger->error('Could not retrieve last order id');

                return $resultRedirect->setPath($redirectUrlSuccess);
            }
            $order = $this->salesOrderFactory->create();
            $order->loadByIncrementId((string)$lastIncrementId);

            $payment = $this->payplugHelper->getOrderPayment((string)$lastIncrementId)->retrieve();

            //If this is the deferred standard paiement then return the user on the success checkout
            if (!$payment->is_paid && $this->isAuthorizedOnlyStandardPaiement($order)) {
                return $resultRedirect->setPath($redirectUrlSuccess);
            }

            if (!$payment->is_paid && !$this->isOneyPending($payment)) {
                $this->payplugHelper->cancelOrderAndInvoice($order);

                $failureMessage = $this->_request->getParam(
                    'failure_message',
                    (string)__('The transaction was aborted and your card has not been charged')
                );

                if (!empty($failureMessage)) {
                    $this->messageManager->addErrorMessage($failureMessage);
                }

                $this->getCheckout()->restoreQuote();

                return $resultRedirect->setPath($redirectUrlCart);
            }

            if (!$order->getId()) {
                $this->logger->error(sprintf('Could not retrieve order with id %s', $lastIncrementId));

                return $resultRedirect->setPath($redirectUrlSuccess);
            }

            $this->payplugHelper->checkPaymentFailureAndAbortPayment($order);
            $order = $this->payplugHelper->updateOrder($order);

            if ($this->payplugHelper->isOrderValidated($order)) {
                return $resultRedirect->setPath($redirectUrlSuccess);
            } else {
                return $this->resultFactory
                    ->create(ResultFactory::TYPE_FORWARD)
                    ->setParams([
                        'is_canceled_by_provider' => true,
                    ])
                    ->forward('cancel');
            }
        } catch (PayplugException $e) {
            $this->logger->error($e->__toString());

            return $resultRedirect->setPath($redirectUrlSuccess);
        } catch (OrderAlreadyProcessingException $e) {
            // Order is already being processed (by IPN or admin update button)
            // Redirect to success page
            // No need to log as it is not an error case
            return $resultRedirect->setPath($redirectUrlSuccess);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return $resultRedirect->setPath($redirectUrlSuccess);
        }
    }

    /**
     * Return true if we are paying with oney and the payment isn't rejected but waiting for approval
     */
    public function isOneyPending(?Payment $payment): bool
    {
        if ($payment && isset($payment->payment_method)) {
            $paymentMethod = $payment->payment_method;
            if ($payment->is_paid === false && isset($paymentMethod['is_pending']) && isset($paymentMethod['type'])) {
                return (str_contains($paymentMethod['type'], 'oney') && $paymentMethod['is_pending'] === true);
            }
        }

        return false;
    }

    /**
     * Return true if the paiement methode was standard deferred
     */
    public function isAuthorizedOnlyStandardPaiement(?OrderInterface $order): bool
    {
        if ($order && $order->getPayment()) {
            return ($this->config->isStandardPaymentModeDeferred() && $order->getPayment()->getMethod() === StandardConfig::METHOD_CODE);
        }

        return false;
    }
}
