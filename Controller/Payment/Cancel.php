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
use Magento\Framework\Url\DecoderInterface as UrlDecoderInterface;
use Magento\Sales\Model\OrderFactory;
use Payplug\Payments\Gateway\Config\InstallmentPlan as InstallmentPlanConfig;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Service\GetCurrentOrder;

class Cancel extends AbstractPayment
{
    /**
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

            $lastIncrementId = $order->getIncrementId();

            if ($order->getPayment()->getMethod() === InstallmentPlanConfig::METHOD_CODE) {
                $orderPaymentModel = $this->payplugHelper->getOrderInstallmentPlan((string)$lastIncrementId);
            } else {
                $orderPaymentModel = $this->payplugHelper->getOrderPayment((string)$lastIncrementId);
            }

            $payment = $orderPaymentModel->retrieve(
                $orderPaymentModel->getScopeId($order),
                $orderPaymentModel->getScope($order)
            );

            if (empty($payment->failure->code) || $payment->failure->code !== 'canceled') {
                return $this->getResultRedirect();
            }

            $this->payplugHelper->cancelOrderAndInvoice($order);

            $failureMessage = $this->_request->getParam(
                'failure_message',
                __('The transaction was aborted and your card has not been charged')
            );
            if (!empty($failureMessage)) {
                $this->messageManager->addErrorMessage($failureMessage);
            }

            $this->getCheckout()->restoreQuote();

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
}
