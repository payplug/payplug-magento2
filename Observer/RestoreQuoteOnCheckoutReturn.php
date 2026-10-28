<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Payplug\Payments\Gateway\Config\Amex as AmexConfig;
use Payplug\Payments\Gateway\Config\Standard as StandardConfig;
use Payplug\Payments\Helper\Config as PayplugConfigHelper;
use Payplug\Payments\Helper\Data as PayplugDataHelper;
use Payplug\Payments\Logger\Logger as PayplugLogger;
use Throwable;

class RestoreQuoteOnCheckoutReturn implements ObserverInterface
{
    /**
     * @param CheckoutSession $checkoutSession
     * @param OrderRepositoryInterface $orderRepository
     * @param StoreManagerInterface $storeManager
     * @param PayplugDataHelper $payplugDataHelper
     * @param PayplugConfigHelper $payplugConfigHelper
     * @param PayplugLogger $payplugLogger
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly PayplugDataHelper $payplugDataHelper,
        private readonly PayplugConfigHelper $payplugConfigHelper,
        private readonly PayplugLogger $payplugLogger
    ) {
    }

    /**
     * Cancel the order and restore the quote when the customer goes back to the checkout before submitting any card
     *
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer): void
    {
        $hasQuoteItems = $this->checkoutSession->getQuote()->getItemsCount() > 0;

        if ($hasQuoteItems === true) {
            return;
        }

        $order = $this->checkoutSession->getLastRealOrder();

        if (!$order->getId()) {
            return;
        }

        $isPendingPayment = $order->getState() === Order::STATE_PENDING_PAYMENT;
        $isCurrentWebsite = (int)$order->getStore()->getWebsiteId() === (int)$this->storeManager->getWebsite()->getId();

        if ($isPendingPayment === false || $isCurrentWebsite === false) {
            return;
        }

        $paymentMethod = $order->getPayment()?->getMethod();
        $isAmexPayment = $paymentMethod === AmexConfig::METHOD_CODE;
        $isHostedStandardPayment = $paymentMethod === StandardConfig::METHOD_CODE
            && $this->payplugConfigHelper->isIntegrated() === false;

        if ($isAmexPayment === false && $isHostedStandardPayment === false) {
            return;
        }

        try {
            $orderPayment = $this->payplugDataHelper->getOrderPayment($order->getIncrementId());

            if ($orderPayment->isHostedFieldsPayment() === true) {
                return;
            }

            $payplugPayment = $orderPayment->retrieve(
                $orderPayment->getScopeId($order),
                $orderPayment->getScope($order)
            );

            $isPaid = !empty($payplugPayment->is_paid);
            $hasSubmittedCard = !empty($payplugPayment->card->last4);

            if ($isPaid === true || $hasSubmittedCard === true) {
                return;
            }

            $this->payplugDataHelper->cancelOrderAndInvoice($order, false);

            $isCanceled = $this->orderRepository->get($order->getEntityId())->getState() === Order::STATE_CANCELED;

            if ($isCanceled === false) {
                return;
            }

            $this->checkoutSession->restoreQuote();
        } catch (Throwable $e) {
            $this->payplugLogger->error('Could not restore the quote on checkout return', [
                'order' => $order->getId(),
                'message' => $e->getMessage(),
            ]);
        }
    }
}
