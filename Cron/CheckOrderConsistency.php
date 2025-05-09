<?php

declare(strict_types=1);

namespace Payplug\Payments\Cron;

use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Resource\Payment as ResourcePayment;

class CheckOrderConsistency
{
    /**
     * Check all payplug orders up to X hours old in order to update them
     */
    public const PAST_HOURS_TO_CHECK = 4;

    public function __construct(
        private Logger $logger,
        private SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        private OrderPaymentRepositoryInterface $paymentRepository,
        private OrderRepositoryInterface $orderRepository,
        private Data $payplugHelper
    ) {
    }

    /**
     * Loop through all payplug orders from the past 4hours & awaiting payment
     */
    public function execute(): void
    {
        $this->logger->info('Running the CheckOrderConsistency cron');

        $magentoOrdersPayments = $this->getCheckablePayplugOrderPaymentsList();

        if (count($magentoOrdersPayments) >= 0) {
            $this->logger->info(
                sprintf(
                    '%s payplug related orders are less than %s hours old and are awaiting payments.',
                    count($magentoOrdersPayments),
                    self::PAST_HOURS_TO_CHECK
                )
            );
        } else {
            $this->logger->info('No payplug related orders found.');
        }

        // Check if the orders awaiting paiement are in the payplug processing state in the API
        foreach ($magentoOrdersPayments as $magentoOrdersPayment) {
            $this->logger->info(sprintf('Trying to update order_id %s', $magentoOrdersPayment->getParentId()));
            $magentoOrder = $this->orderRepository->get($magentoOrdersPayment->getParentId());
            $payplugPayment = $this->getPayplugPaymentFromApiByIncrementId($magentoOrder);
            if ($payplugPayment) {
                $paymentId = $payplugPayment->id ?: '';
                $payplugOrderPayment = $this->payplugHelper->getOrderPayment($magentoOrder->getIncrementId());
                // If the payment is not processing in the API it mean that the state of the order can be updated
                if (!$payplugOrderPayment->isProcessing($payplugPayment)) {
                    $this->logger->info(
                        sprintf(
                            'Payplug payment_id %s is not processing in the API and will be updated in magento.',
                            $paymentId
                        )
                    );
                    $this->payplugHelper->checkPaymentFailureAndAbortPayment($magentoOrder);
                    $this->payplugHelper->updateOrder($magentoOrder);
                } else {
                    // Payment is still processing (not paid and not failure on the api) we just log it
                    $this->logger->info(
                        sprintf(
                            'Payplug payment_id %s is still processing in the API for magento order_id %s.',
                            $paymentId,
                            $magentoOrder->getEntityId()
                        )
                    );
                }
            } else {
                // The magento order couldn't be matched to any payplug order
                $this->logger->info(
                    sprintf(
                        'No payplug payment found for the magento order %s.',
                    $magentoOrder->getEntityId()
                    )
                );
            }
        }

        $this->logger->info(sprintf('The %s cron is finished.', get_class($this)));
    }

    public function getPayplugPaymentFromApiByIncrementId(OrderInterface $magentoOrder): ?ResourcePayment
    {
        try {
            $payplugOrderPayment = $this->payplugHelper->getOrderPayment($magentoOrder->getIncrementId());
        } catch (NoSuchEntityException $e) {
            return null;
        }

        if (!$payplugOrderPayment->getId()) {
            $this->logger->error('Cannot find a PayplugOrderPayment for the magento order %s.', $magentoOrder->getEntityId());

            return null;
        }

        try {
            $payment = $payplugOrderPayment->retrieve((int)$magentoOrder->getStore()->getWebsiteId(), ScopeInterface::SCOPE_WEBSITES);
        } catch (PayplugException $e) {
            $this->logger->error($e->__toString());

            return null;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            if (str_contains($e->getMessage(), 'Forbidden error')) {
                $this->logger->error(
                    sprintf(
                        'The order entity id %s cannot be retrieved anymore from payplug Api.',
                        $magentoOrder->getEntityId()
                    )
                );
            }

            return null;
        }

        return $payment;
    }

    /**
     * Get all payplug orders from the past 4hours & awaiting payment
     *
     * @return OrderPaymentInterface[]|null
     */
    public function getCheckablePayplugOrderPaymentsList(): ?array
    {
        $delay = (new \DateTime())->modify('-' . self::PAST_HOURS_TO_CHECK . ' hours')->format('Y-m-d H:i:s');

        // Get all the order within a said delay (sales_order table)
        $searchOrderCriteria = $this->searchCriteriaBuilderFactory->create()
            ->addFilter('created_at', $delay, 'gteq')
            ->create();

        $orderIds = $this->orderRepository->getList($searchOrderCriteria)->getAllIds();

        if (empty($orderIds)) {
            return [];
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
