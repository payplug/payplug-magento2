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
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Url\DecoderInterface as UrlDecoderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Payplug\Payments\Gateway\Config\Standard as StandardConfig;
use Payplug\Payments\Helper\Config as PayplugConfigHelper;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Observer\MarkOrderPaymentAsHeadless;
use Payplug\Payments\Service\GetCurrentOrder;
use Throwable;

class Cancel extends AbstractPayment
{
    /**
     * @param FormKeyValidator $formKeyValidator
     * @param RequestInterface $request
     * @param GetCurrentOrder $getCurrentOrder
     * @param UrlDecoderInterface $urlDecoder
     * @param PayplugConfigHelper $payplugConfigHelper
     * @param CustomerSession $customerSession
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
        private readonly PayplugConfigHelper $payplugConfigHelper,
        private readonly CustomerSession $customerSession,
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
        $resultRedirect = $this->getResultRedirect();

        try {
            $order = $this->getCurrentOrder->execute();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            return $resultRedirect;
        }

        $isFormKeyValidated = $this->formKeyValidator->validate($this->request);
        $isHeadless = (bool) $order->getPayment()?->getAdditionalInformation(
            MarkOrderPaymentAsHeadless::PAYPLUG_IS_HEADLESS
        );

        if ($isHeadless === false && $isFormKeyValidated === false) {
            $this->logger->error('Form key validation failed');
            return $resultRedirect;
        }

        if ($isHeadless === false && $order->getCustomerId() !== $this->customerSession->getCustomerId()) {
            $this->logger->error('Order customer ID does not match session customer ID');
            return $resultRedirect;
        }

        $orderPaymentMethod = $order->getPayment()?->getMethod();

        if ($orderPaymentMethod === null
            || $this->payplugHelper->isCodePayplugPayment($orderPaymentMethod) === false
            || in_array($order->getState(), [Order::STATE_CANCELED, Order::STATE_PENDING_PAYMENT], true) === false
        ) {
            return $resultRedirect;
        }

        $isMethodSupportingCancelFailureCode = $this->isMethodSupportingCancelFailureCode($orderPaymentMethod);
        $isStandardMethodWithPopin = $orderPaymentMethod === StandardConfig::METHOD_CODE
            && $this->payplugConfigHelper->isEmbedded() === true;

        $checkFailureCode = $isMethodSupportingCancelFailureCode === true && $isStandardMethodWithPopin === false;

        try {
            $this->payplugHelper->cancelOrderAndInvoice($order, $checkFailureCode);
        } catch (Throwable $e) {
            $this->logger->error(sprintf('Could not cancel order ID %s', $order->getId()));
            $this->logger->error($e->getMessage());
        }

        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
        $this->checkoutSession->restoreQuote();

        $failureMessage = $this->_request->getParam(
            'failure_message',
            __('The transaction was aborted and your card has not been charged')
        );

        $this->messageManager->addErrorMessage($failureMessage);

        return $this->getResultRedirect();
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
