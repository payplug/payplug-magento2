<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\Payment;

use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\OrderFactory;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Exception\OrderAlreadyProcessingException;
use Payplug\Resource\Payment;

class PaymentReturn extends AbstractPayment
{
    /**
     * Handle return from PayPlug payment page
     *
     * @return mixed
     */
    public function execute(): mixed
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
            $order->loadByIncrementId($lastIncrementId);

            $payment = $this->payplugHelper->getOrderPayment($lastIncrementId)->retrieve();

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
     *
     * @param Payment|null $payment
     *
     * @return bool
     */
    public function isOneyPending(?Payment $payment): bool
    {
        if ($payment) {
            $paymentMethod = $payment->payment_method;
            if ($payment->is_paid === false && $paymentMethod['is_pending'] && $paymentMethod['type']) {
                return (str_contains($paymentMethod['type'], 'oney') && $paymentMethod['is_pending'] === true);
            }
        }

        return false;
    }
}
