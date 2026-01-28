<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\OrderFactory;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Exception\OrderAlreadyProcessingException;
use Payplug\Payments\Gateway\Config\InstallmentPlan as InstallmentPlanConfig;
use Payplug\Payments\Gateway\Config\Wero as WeroConfig;
use Payplug\Payments\Gateway\Config\Standard as StandardConfig;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Service\GetCurrentOrder;

class PaymentReturn extends AbstractPayment
{
    /**
     * @param Context $context
     * @param Session $checkoutSession
     * @param OrderFactory $salesOrderFactory
     * @param Logger $logger
     * @param Data $payplugHelper
     * @param Config $config
     * @param CartRepositoryInterface $cartRepository
     * @param GetCurrentOrder $getCurrentOrder
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderFactory $salesOrderFactory,
        Logger $logger,
        Data $payplugHelper,
        protected Config $config,
        protected CartRepositoryInterface $cartRepository,
        protected GetCurrentOrder $getCurrentOrder
    ) {
        parent::__construct($context, $checkoutSession, $salesOrderFactory, $logger, $payplugHelper);
    }

    /**
     * Handle return from PayPlug payment page
     *
     * @return Redirect|ResultInterface|Json|ResponseInterface
     */
    public function execute()
    {
        $resultSuccessRedirect = $this->resultRedirectFactory->create();
        $customAfterSuccessUrl = $this->_request->getParam('afterSuccessUrl');

        if ($customAfterSuccessUrl) {
            $resultSuccessRedirect->setUrl($customAfterSuccessUrl);
        } else {
            $resultSuccessRedirect->setPath('checkout/onepage/success');
        }

        $resultFailureRedirect = $this->resultRedirectFactory->create();
        $customAfterFailureUrl = $this->_request->getParam('afterFailureUrl');

        if ($customAfterFailureUrl) {
            $resultFailureRedirect->setUrl($customAfterFailureUrl);
        } else {
            $resultFailureRedirect->setPath('checkout/cart');
        }

        try {
            $order = $this->getCurrentOrder->execute();
            $lastIncrementId = $order->getIncrementId();
            $isInstallment = false;

            if ($order->getPayment()->getMethod() === InstallmentPlanConfig::METHOD_CODE) {
                $isInstallment = true;
                $orderPaymentModel = $this->payplugHelper->getOrderInstallmentPlan((string)$lastIncrementId);
            } else {
                $orderPaymentModel = $this->payplugHelper->getOrderPayment((string)$lastIncrementId);
            }

            $payment = $orderPaymentModel->retrieve(
                $orderPaymentModel->getScopeId($order),
                $orderPaymentModel->getScope($order)
            );

            if ($order->getPayment()?->getMethod() === WeroConfig::METHOD_CODE
                && !$payment->is_paid && !$payment->failure
            ) {
                /**
                 * Wero does not provide any failure code to payplug when the user aborts payment
                 * So Payplug Redirect to PaymentReturn by default
                 * Here is a workaround to forward to Cancel action
                 */
                $this->_forward('cancel');
                return $this->getResponse();
            }

            if ($isInstallment) {
                if ($payment->failure) {
                    $this->prepareErrorOnPayment($order);

                    return $resultFailureRedirect;
                }

                return $resultSuccessRedirect;
            }

            /**
             * If this is the deferred standard paiement and authorized, then update the order and return the user
             * on the success checkout
             */
            if (!$payment->is_paid
                && $this->isAuthorizedOnlyStandardPayment($order)
                && $payment->authorization
                && $payment->authorization->authorized_at !== null
            ) {
                if ($this->payplugHelper->canUpdatePayment($order)) {
                    $this->payplugHelper->updateOrder($order);
                    $standardDeferredQuote = $this->payplugHelper->getStandardDeferredQuote($payment);
                    $this->payplugHelper->saveAutorizationInformationOnQuote($standardDeferredQuote, $payment);
                }

                return $resultSuccessRedirect;
            }

            if (!$payment->is_paid
                && !$orderPaymentModel->isProcessing($payment)
                && !$this->payplugHelper->isOneyPending($payment)
            ) {
                $this->prepareErrorOnPayment($order);

                return $resultFailureRedirect;
            }

            if (!$order->getId()) {
                $this->logger->error(sprintf('Could not retrieve order with id %s', $lastIncrementId));

                return $resultSuccessRedirect;
            }

            $this->payplugHelper->checkPaymentFailureAndAbortPayment($order);
            $order = $this->payplugHelper->updateOrder($order);

            if ($this->payplugHelper->isOrderValidated($order)) {
                return $resultSuccessRedirect;
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

            return $resultSuccessRedirect;
        } catch (OrderAlreadyProcessingException $e) {
            // Order is already being processed (by IPN or admin update button)
            // Redirect to success page
            // No need to log as it is not an error case
            return $resultSuccessRedirect;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return $resultSuccessRedirect;
        }
    }

    /**
     * Prepare error on payment
     *
     * @param OrderInterface $order
     * @return void
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function prepareErrorOnPayment(OrderInterface $order): void
    {
        $this->payplugHelper->cancelOrderAndInvoice($order);

        $failureMessage = $this->_request->getParam(
            'failure_message',
            (string)__('The transaction was aborted and your card has not been charged')
        );

        if (!empty($failureMessage)) {
            $this->messageManager->addErrorMessage($failureMessage);
        }

        $this->getCheckout()->restoreQuote();
    }

    /**
     * Return true if the paiement methode was standard deferred
     *
     * @param OrderInterface|null $order
     * @return bool
     */
    public function isAuthorizedOnlyStandardPayment(?OrderInterface $order): bool
    {
        return $this->isAuthorizedOnlyStandardPaymentFromMethod($order?->getPayment()?->getMethod());
    }

    /**
     * Return true if the paiement methode was standard deferred
     *
     * @param string|null $method
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isAuthorizedOnlyStandardPaymentFromMethod(?string $method): bool
    {
        return $method === StandardConfig::METHOD_CODE && $this->config->isStandardPaymentModeDeferred();
    }
}
