<?php

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\OrderFactory;

class GetCurrentOrderIncrementId
{
    public function __construct(
        protected RequestInterface $request,
        protected Session $checkoutSession,
        protected OrderFactory $salesOrderFactory,
        protected QuoteRepository $quoteRepository
    ) {
    }

    public function getLastRealOrder(): ?OrderInterface
    {
        $order = $this->getLastRealOrderByCheckoutSession();
        if ($order) {

            return $order;
        }

        $order = $this->getLastRealOrderFromRequestQuoteId();
        if ($order) {

            return $order;
        }

        $order = $this->getLastRealOrderByCheckoutSessionLastQuoteId();
        if ($order) {

            return $order;
        }

        $order = $this->getLastRealOrderByCheckoutSessionQuoteId();
        if ($order) {

            return $order;
        }

        return null;
    }

    /**
     * Second way to grab the last real order
     * Typically if we have a return, we test against the quote_id and its reserved order id
     * We grab the order with that and return it
     *
     * @return OrderInterface|null
     * @throws NoSuchEntityException
     */
    public function getLastRealOrderFromRequestQuoteId(): ?OrderInterface
    {
        $quoteId = $this->request->getParam('quote_id');

        if (!$quoteId) {

            return null;
        }

        $quote = $this->quoteRepository->get($quoteId);

        if ($quote->getReservedOrderId()) {
            $order = $this->salesOrderFactory->create();
            $order->loadByIncrementId($quote->getReservedOrderId());

            if ($order->getId()) {

                return $order;
            }
        }

        return null;
    }

    /**
     * First way to grab the last order
     * We use the checkout session to directly grab the last order if it exists
     *
     * @return OrderInterface|null
     */
    public function getLastRealOrderByCheckoutSession(): ?OrderInterface
    {
        $lastIncrementId = $this->checkoutSession->getLastRealOrder()->getIncrementId();

        if (!$lastIncrementId) {

            return null;
        }

        $order = $this->salesOrderFactory->create();
        $order->loadByIncrementId($lastIncrementId);

        if ($order->getId()) {

            return $order;
        }

        return null;
    }

    /**
     * Third way to grab the last order
     * We use the checkout session to get the last_quote_id if it exists
     * We retrieve the quote and its reserved order id and extract the order from there
     *
     * @return OrderInterface|null
     */
    public function getLastRealOrderByCheckoutSessionLastQuoteId(): ?OrderInterface
    {
        $lastQuoteId = $this->checkoutSession->getLastQuoteId();

        if (!$lastQuoteId) {

            return null;
        }

        $quote = $this->quoteRepository->get($lastQuoteId);

        if ($quote->getReservedOrderId()) {
            $order = $this->salesOrderFactory->create();
            $order->loadByIncrementId($quote->getReservedOrderId());

            if ($order->getId()) {

                return $order;
            }
        }

        return null;
    }

    /**
     * Fourth way to grab the last order
     * We use the checkout session to get the quote_id if it exists
     * We retrieve the quote and its reserved order id and extract the order from there
     *
     * @return OrderInterface|null
     */
    public function getLastRealOrderByCheckoutSessionQuoteId(): ?OrderInterface
    {
        $quoteId = $this->checkoutSession->getQuoteId();

        if (!$quoteId) {

            return null;
        }

        $quote = $this->quoteRepository->get($quoteId);

        if ($quote->getReservedOrderId()) {
            $order = $this->salesOrderFactory->create();
            $order->loadByIncrementId($quote->getReservedOrderId());

            if ($order->getId()) {

                return $order;
            }
        }

        return null;
    }
}
