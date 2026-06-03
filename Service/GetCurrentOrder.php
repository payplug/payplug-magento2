<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\OrderFactory;

class GetCurrentOrder
{
    /**
     * @param RequestInterface $request
     * @param Session $checkoutSession
     * @param OrderFactory $salesOrderFactory
     * @param CartRepositoryInterface $cartRepositoryInterface
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly Session $checkoutSession,
        private readonly OrderFactory $salesOrderFactory,
        private readonly CartRepositoryInterface $cartRepositoryInterface,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
    ) {
    }

    /**
     * Attempt to retrieve the currently active order using multiple strategies.
     *
     * @throws LocalizedException
     */
    public function execute(): OrderInterface
    {
        // 1) Try to load via last real order increment ID
        $order = $this->loadOrderByIncrementId(
            $this->checkoutSession->getLastRealOrder()?->getIncrementId()
        );

        if ($order) {
            return $order;
        }

        // 2) Try to load via the first available quote ID from session
        $quoteId = $this->checkoutSession->getLastQuoteId() ?? $this->checkoutSession->getQuoteId();
        $order = $this->loadOrderByQuoteId((int) $quoteId);

        if ($order !== null) {
            return $order;
        }

        // 3) Try to load via masked quote id from query string
        $requestMaskedQuoteId = (string) $this->request->getParam('masked_quote_id');

        if ($requestMaskedQuoteId) {
            try {
                $quoteId = $this->maskedQuoteIdToQuoteId->execute($requestMaskedQuoteId);
            } catch (NoSuchEntityException) {
                throw new LocalizedException(__('Could not retrieve last order'));
            }

            $order = $this->loadOrderByQuoteId($quoteId);

            if ($order !== null) {
                return $order;
            }
        }

        // If all attempts failed:
        throw new LocalizedException(__('Could not retrieve last order'));
    }

    /**
     * Helper method to load an order from a given increment ID, or return null if not found.
     *
     * @param string|null $incrementId
     * @return OrderInterface|null
     */
    private function loadOrderByIncrementId(?string $incrementId): ?OrderInterface
    {
        if (!$incrementId) {
            return null;
        }

        $order = $this->salesOrderFactory->create();
        $order->loadByIncrementId($incrementId);

        return $order->getId() ? $order : null;
    }

    /**
     * Helper method to load an order from a quote's reserved order ID, or return null if not found.
     *
     * @param int|null $quoteId
     * @return OrderInterface|null
     */
    private function loadOrderByQuoteId(?int $quoteId): ?OrderInterface
    {
        if (!$quoteId || $quoteId == 0) {
            return null;
        }

        try {
            $quote = $this->cartRepositoryInterface->get($quoteId);
        } catch (NoSuchEntityException) {
            return null;
        }

        $reservedOrderId = $quote->getReservedOrderId();

        if (!$reservedOrderId) {
            return null;
        }

        return $this->loadOrderByIncrementId($reservedOrderId);
    }
}
