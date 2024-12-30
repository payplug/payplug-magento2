<?php

declare(strict_types=1);

namespace Payplug\Payments\Cron;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Payplug\Payments\Logger\Logger;

class CheckOrderConsistency
{
    public const PAST_HOURS_TO_CHECK = 4;

    public function __construct(
        private Logger $logger,
        private SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        private OrderPaymentRepositoryInterface $paymentRepository,
        private OrderRepository $orderRepository
    ) {
    }

    /**
     * Loop through all payplug orders from the past 4hours & awaiting payment
     */
    public function execute(): void
    {
        $this->logger->info('Running the CheckOrderConsistency cron');

        $ordersPayments = $this->getCheckablePayplugOrderPaymentsList();

        $this->logger->info('The CheckOrderConsistency cron is over');
    }

    /**
     * Get all payplug orders from the past 4hours & awaiting payment
     *
     * @return OrderPaymentInterface[]|null
     */
    public function getCheckablePayplugOrderPaymentsList(): ?array
    {
        $fourHoursAgo = (new \DateTime())->modify('-' . self::PAST_HOURS_TO_CHECK . ' hours')->format('Y-m-d H:i:s');

        // Get all the order of the past 4 hours (sales_order table)
        $searchOrderCriteria = $this->searchCriteriaBuilderFactory->create()
            ->addFilter('created_at', $fourHoursAgo, 'gteq')
            ->create();
        /** @var Order[] $orders */
        $orders = $this->orderRepository->getList($searchOrderCriteria)->getItems();

        $orderIds = [];
        foreach ($orders as $order) {
            $orderIds[] = $order->getId();
        }

        // From the orders, get all the matching order payment that are not paid and using the payplug method (sales_order_payment table)
        $searchOrderPaymentCriteria = $this->searchCriteriaBuilderFactory->create()
            ->addFilter('parent_id', $orderIds, 'in')
            ->addFilter('method', '%payplug%', 'like')
            ->addFilter('base_amount_paid', null, 'null')
            ->create();

        return $this->paymentRepository->getList($searchOrderPaymentCriteria)->getItems();
    }
}
