<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Cron;

use DateMalformedStringException;
use DateTime;
use Exception;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Exception\OrderAlreadyProcessingException;
use Payplug\Payments\Helper\Data as PayplugDataHelper;
use Payplug\Payments\Logger\Logger;
use Payplug\Resource\Payment as ResourcePayment;

class CheckOrderConsistency
{
    public const WINDOW_FROM_HOURS = 4;
    public const WINDOW_TO_MINUTES = 15;

    /**
     * @param Logger $logger
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param OrderPaymentRepositoryInterface $paymentRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param PayplugDataHelper $payplugHelper
     */
    public function __construct(
        private readonly Logger $logger,
        private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        private readonly OrderPaymentRepositoryInterface $paymentRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PayplugDataHelper $payplugHelper
    ) {
    }

    /**
     * Loop through all payplug orders from the past 4hours & awaiting payment
     *
     * @throws DateMalformedStringException
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws LocalizedException
     * @throws OrderAlreadyProcessingException
     */
    public function execute(): void
    {
        $this->logger->info('Running the CheckOrderConsistency cron');

        $magentoOrderPayments = $this->getCheckablePayplugOrderPayments();

        $this->logger->info(
            sprintf(
                '%s payplug related orders are less than %s hours old and are awaiting payments.',
                count($magentoOrderPayments),
                self::WINDOW_FROM_HOURS
            )
        );

        foreach ($magentoOrderPayments as $magentoOrdersPayment) {
            $this->logger->info(sprintf('Trying to update order_id %s', $magentoOrdersPayment->getParentId()));
            $magentoOrder = $this->orderRepository->get($magentoOrdersPayment->getParentId());
            $payplugPaymentResource = $this->getPayplugPaymentResourceByOrder($magentoOrder);

            if ($payplugPaymentResource === null) {
                $this->logger->info(sprintf(
                    'No payplug payment found for the magento order ID %s.',
                    $magentoOrder->getEntityId()
                ));
                continue;
            }

            $paymentId = (string) $payplugPaymentResource->id;

            $payplugOrderPayment = $this->payplugHelper->getOrderPayment($magentoOrder->getIncrementId());

            // If the payment is processing in the API it mean that the state of the order cannot be updated
            if ($payplugOrderPayment->isProcessing($payplugPaymentResource) === true) {
                $this->logger->info(sprintf(
                    'Payplug payment_id %s is still processing in the API for magento order_id %s.',
                    $paymentId,
                    $magentoOrder->getEntityId()
                ));

                continue;
            }

            $this->logger->info(sprintf(
                'Payplug payment_id %s is not processing in the API and will be updated in magento.',
                $paymentId
            ));

            $this->payplugHelper->checkPaymentFailureAndAbortPayment($magentoOrder);
            $this->payplugHelper->updateOrder($magentoOrder);
        }

        $this->logger->info(sprintf('The %s cron is finished.', get_class($this)));
    }

    /**
     * Get the payplug payment resource from the API by order
     *
     * @param OrderInterface $order
     * @return ResourcePayment|null
     */
    public function getPayplugPaymentResourceByOrder(OrderInterface $order): ?ResourcePayment
    {
        try {
            $payplugOrderPayment = $this->payplugHelper->getOrderPayment($order->getIncrementId());
        } catch (NoSuchEntityException) {
            return null;
        }

        if (!$payplugOrderPayment->getId()) {
            $this->logger->error(
                'Cannot find a PayplugOrderPayment for the magento order %s.',
                [(string) $order->getEntityId()]
            );

            return null;
        }

        try {
            $payment = $payplugOrderPayment->retrieve(
                (int)$order->getStore()->getWebsiteId(),
                ScopeInterface::SCOPE_WEBSITES
            );
        } catch (PayplugException $e) {
            $this->logger->error($e->__toString());

            return null;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            if (str_contains($e->getMessage(), 'Forbidden error')) {
                $this->logger->error(
                    sprintf(
                        'The order entity id %s cannot be retrieved anymore from payplug Api.',
                        $order->getEntityId()
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
     * @throws DateMalformedStringException
     */
    public function getCheckablePayplugOrderPayments(): ?array
    {
        $from = (new DateTime())->modify('-' . self::WINDOW_FROM_HOURS . ' hours')->format('Y-m-d H:i:s');
        $to = (new DateTime())->modify('-' . self::WINDOW_TO_MINUTES . ' minutes')->format('Y-m-d H:i:s');

        // Get all the order within a said delay (sales_order table)
        $searchOrderCriteria = $this->searchCriteriaBuilderFactory->create()
            ->addFilter(OrderInterface::CREATED_AT, $from, 'gteq')
            ->addFilter(OrderInterface::CREATED_AT, $to, 'lteq')
            ->create();

        $orderIds = $this->orderRepository->getList($searchOrderCriteria)->getAllIds();

        if (empty($orderIds)) {
            return [];
        }

        /**
         * From the orders, get all the matching order payment that are not paid and using the payplug method
         * (sales_order_payment table)
         */
        $searchOrderPaymentCriteria = $this->searchCriteriaBuilderFactory->create()
            ->addFilter('parent_id', $orderIds, 'in')
            ->addFilter('method', '%payplug%', 'like')
            ->addFilter('base_amount_paid', null, 'null')
            ->create();

        return $this->paymentRepository->getList($searchOrderPaymentCriteria)->getItems();
    }
}
