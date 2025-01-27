<?php

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
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
     * @return OrderInterface|null
     * @throws \Exception
     */
    public function execute(): ?OrderInterface
    {
        // 1) If we have an increment ID in the "last real order" from session
        $order = $this->tryLoadOrderByIncrementId(
            $this->checkoutSession->getLastRealOrder()->getIncrementId()
        );

        if ($order) {
            return $order;
        }

        // 2) If we have a quote ID in the request, load its order
        $order = $this->tryLoadOrderByQuoteId(
            $this->request->getParam('quote_id')
        );

        if ($order) {
            return $order;
        }

        // 3) If we have a "last quote ID" in session, load its order
        $order = $this->tryLoadOrderByQuoteId(
            $this->checkoutSession->getLastQuoteId()
        );

        if ($order) {
            return $order;
        }

        // 4) If we have a "quote ID" in session, load its order
        $order = $this->tryLoadOrderByQuoteId(
            $this->checkoutSession->getQuoteId()
        );

        if ($order) {
            return $order;
        }

        // If all attempts failed:
        throw new \Exception('Could not retrieve last order id');
    }

    /**
     * Helper method to load an order from a given increment ID, or return null if not found.
     */
    private function tryLoadOrderByIncrementId(?string $incrementId): ?OrderInterface
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
    private function tryLoadOrderByQuoteId(?int $quoteId): ?OrderInterface
    {
        if (!$quoteId) {
            return null;
        }

        $quote = $this->cartRepositoryInterface->get($quoteId);
        $reservedOrderId = $quote->getReservedOrderId();

        if (!$reservedOrderId) {
            return null;
        }

        // Reuse the same loading logic by increment ID
        return $this->tryLoadOrderByIncrementId($reservedOrderId);
    }
}
