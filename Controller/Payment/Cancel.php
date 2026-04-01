<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Controller\Payment;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Url\DecoderInterface as UrlDecoderInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Service\GetCurrentOrder;
use Throwable;

class Cancel extends AbstractPayment
{
    /**
     * @param FormKeyValidator $formKeyValidator
     * @param RequestInterface $request
     * @param GetCurrentOrder $getCurrentOrder
     * @param UrlDecoderInterface $urlDecoder
     * @param Context $context
     * @param Session $checkoutSession
     * @param OrderFactory $salesOrderFactory
     * @param Logger $logger
     * @param Data $payplugHelper
     */
    public function __construct(
        private readonly FormKeyValidator $formKeyValidator,
        private readonly RequestInterface $request,
        private readonly GetCurrentOrder $getCurrentOrder,
        private readonly UrlDecoderInterface $urlDecoder,
        Context $context,
        Session $checkoutSession,
        OrderFactory $salesOrderFactory,
        Logger $logger,
        Data $payplugHelper
    ) {
        parent::__construct($context, $checkoutSession, $salesOrderFactory, $logger, $payplugHelper);
    }

    /**
     * Cancel payment
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        try {
            $order = $this->getCurrentOrder->execute();
            $orderFromSession = $this->checkoutSession->getLastRealOrder();
            $isFormKeyValidated = $this->formKeyValidator->validate($this->request);

            if ($order !== null && $this->payplugHelper->isPaymentFailure($order) === true) {
                /** Strict validation regardless of order origin (session or query parameter) */
                $this->cancelAndRollback($order);
            } elseif ($isFormKeyValidated === true && $orderFromSession->getStatus() === Order::STATE_PENDING_PAYMENT) {
                /** Fallback validation using order from the checkout session only */
                $this->cancelAndRollback($orderFromSession);
            }

            $failureMessage = $this->_request->getParam(
                'failure_message',
                __('The transaction was aborted and your card has not been charged')
            );

            $this->messageManager->addErrorMessage($failureMessage);

            return $this->getResultRedirect();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());

            return $this->getResultRedirect();
        }
    }

    /**
     * Get result redirect
     *
     * @return Redirect
     */
    private function getResultRedirect(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $afterCancelUrl = $this->request->getParam('afterCancelUrl');
        $redirectToReferer = $this->request->getParam('redirectToReferer');

        if ($redirectToReferer == 1) {
            $resultRedirect->setRefererUrl();
        } elseif ($afterCancelUrl) {
            $afterCancelUrl = $this->urlDecoder->decode($afterCancelUrl);
            $resultRedirect->setUrl($afterCancelUrl);
        } else {
            $resultRedirect->setPath('checkout/cart');
        }

        return $resultRedirect;
    }

    /**
     * Cancel order/invoice, and rollback cart
     *
     * @param OrderInterface $order
     * @return void
     */
    private function cancelAndRollback(OrderInterface $order): void
    {
        try {
            $this->payplugHelper->cancelOrderAndInvoice($order);
        } catch (Throwable) {
            $this->logger->error(sprintf('Could not cancel order ID %1', $order->getId()));
        }

        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
        $this->checkoutSession->restoreQuote();
    }
}
