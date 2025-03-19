<?php

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\OrderFactory;
use Payplug\Payments\Logger\Logger;

class GetCurrentOrder
{
    public function __construct(
        protected RequestInterface $request,
        protected Session $checkoutSession,
        protected OrderFactory $salesOrderFactory,
        protected CartRepositoryInterface $cartRepositoryInterface,
        protected Logger $logger
    ) {
    }

    /**
     * Attempt to retrieve the currently active order using multiple strategies.
     *
     * @throws \Exception
     */
    public function execute(): ?OrderInterface
    {
        // 1) Try to load via last real order increment ID
        $order = $this->loadOrderByIncrementId(
            $this->checkoutSession->getLastRealOrder()?->getIncrementId()
        );

        if ($order) {
            return $order;
        }

        // 2) Try to load via the first available quote ID
        $quoteId = $this->request->getParam('quote_id')
            ?? $this->checkoutSession->getLastQuoteId()
            ?? $this->checkoutSession->getQuoteId();

        $order = $this->loadOrderByQuoteId((int)$quoteId);

        if ($order) {
            return $order;
        }

        // If all attempts failed:
        throw new \Exception('Could not retrieve last order id');
    }

    /**
     * Helper method to load an order from a given increment ID, or return null if not found.
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
     * @throws NoSuchEntityException
     */
    private function loadOrderByQuoteId(?int $quoteId): ?OrderInterface
    {
        if (!$quoteId || $quoteId == 0) {
            return null;
        }

        $quote = $this->cartRepositoryInterface->get($quoteId);
        $reservedOrderId = $quote->getReservedOrderId();

        if (!$reservedOrderId) {
            return null;
        }

        return $this->loadOrderByIncrementId($reservedOrderId);
    }
}
