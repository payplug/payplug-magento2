<?php
/*
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Console\Command;

use DateMalformedStringException;
use DateTime;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Payment\CollectionFactory as OrderPaymentCollectionFactory;
use Payplug\Payments\Gateway\Config\Standard;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class MigrateAuthorizationMetadata extends Command
{
    private const COMMAND_NAME = 'payplug:migrate-authorization-metadata';
    private const IS_AUTHORIZED_KEY = 'is_authorized';
    private const EXPIRES_AT_KEY = 'expires_at';
    private const AUTHORIZED_AMOUNT_KEY = 'authorized_amount';
    private const AUTHORIZED_AT_KEY = 'authorized_at';
    private const ORDER_TABLE_NAME = 'sales_order';
    private const QUOTE_PAYMENT_TABLE_NAME = 'quote_payment';
    private const QUOTE_PAYMENT_ADDITIONAL_INFORMATION_KEY = 'quote_payment_additional_information';
    private const THRESHOLD_DAYS = 10;

    /**
     * @param OrderPaymentCollectionFactory $orderPaymentCollectionFactory
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly OrderPaymentCollectionFactory $orderPaymentCollectionFactory,
        private readonly SerializerInterface $serializer
    ) {
        parent::__construct(self::COMMAND_NAME);
    }

    /**
     * Execute command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(
            sprintf(
                '<info>Migrate authorization metadata from quote to order on last %d days</info>',
                self::THRESHOLD_DAYS
            )
        );

        try {
            $this->migrate($output);
        } catch (Throwable $e) {
            $output->writeln($e->getMessage());
            $output->writeln('<error>Please check the logs for more information</error>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Migrate authorization metadata from quote to order
     *
     * @param OutputInterface $output
     * @return void
     * @throws DateMalformedStringException
     */
    private function migrate(OutputInterface $output): void
    {
        $thresholdDateTime = new DateTime(sprintf('-%d days', self::THRESHOLD_DAYS));
        $formatedThresholdDate = $thresholdDateTime->format(Mysql::TIMESTAMP_FORMAT);

        $orderPaymentCollection = $this->orderPaymentCollectionFactory->create();
        $orderPaymentCollection->addFieldToFilter(
            'main_table.' . OrderPaymentInterface::METHOD,
            Standard::METHOD_CODE
        );
        $orderPaymentCollection->getSelect()->where(
            "JSON_EXTRACT(main_table.additional_information, '$.is_deferred_payment_standard') = true"
        );

        $orderTableName = $orderPaymentCollection->getConnection()->getTableName(self::ORDER_TABLE_NAME);
        $orderPaymentCollection->join(
            ['order' => $orderTableName],
            'main_table.parent_id = order.' . OrderInterface::ENTITY_ID,
            [
                OrderInterface::INCREMENT_ID => 'order.' . OrderInterface::INCREMENT_ID,
                'order_quote_id' => 'order.' . OrderInterface::QUOTE_ID,
            ]
        );
        $orderPaymentCollection->addFieldToFilter('order.' . OrderInterface::STATUS, ['nin' => Order::STATE_CANCELED]);
        $orderPaymentCollection->addFieldToFilter(
            'order.' . OrderInterface::CREATED_AT,
            ['gteq' => $formatedThresholdDate]
        );

        $quotePaymentTableName = $orderPaymentCollection->getConnection()->getTableName(self::QUOTE_PAYMENT_TABLE_NAME);
        $orderPaymentCollection->join(
            ['quote_payment' => $quotePaymentTableName],
            'order.quote_id = quote_payment.quote_id',
            [self::QUOTE_PAYMENT_ADDITIONAL_INFORMATION_KEY => 'quote_payment.additional_information']
        );

        $processedOrderCount = 0;

        /** @var OrderPaymentInterface $orderPayment */
        foreach ($orderPaymentCollection as $orderPayment) {
            $orderIncrementId = $orderPayment->getData(OrderInterface::INCREMENT_ID);
            $quoteAdditionalInformation = $this->serializer->unserialize(
                $orderPayment->getData(self::QUOTE_PAYMENT_ADDITIONAL_INFORMATION_KEY) ?? ''
            );

            $authorizationMetadata = array_filter([
                self::IS_AUTHORIZED_KEY => $quoteAdditionalInformation[self::IS_AUTHORIZED_KEY] ?? null,
                self::AUTHORIZED_AMOUNT_KEY => $quoteAdditionalInformation[self::AUTHORIZED_AMOUNT_KEY] ?? null,
                self::AUTHORIZED_AT_KEY => $quoteAdditionalInformation[self::AUTHORIZED_AT_KEY] ?? null,
                self::EXPIRES_AT_KEY => $quoteAdditionalInformation[self::EXPIRES_AT_KEY] ?? null,
            ]);

            $orderAdditionalInformation = (array) $orderPayment->getAdditionalInformation();

            $diff = array_diff_key($authorizationMetadata, $orderAdditionalInformation);

            if (count($diff) === 0) {
                continue;
            }

            $orderPaymentAdditionalInformation = $orderAdditionalInformation + $authorizationMetadata;
            $orderPaymentAdditionalInformationJson = $this->serializer->serialize($orderPaymentAdditionalInformation);

            $orderPaymentCollection->getConnection()->update(
                $orderPaymentCollection->getMainTable(),
                [OrderPaymentInterface::ADDITIONAL_INFORMATION => $orderPaymentAdditionalInformationJson],
                [OrderPaymentInterface::ENTITY_ID . ' = ?' => $orderPayment->getEntityId()]
            );

            $processedOrderCount++;

            $output->writeLn(sprintf(
                '<comment>Processed Order ID :</comment> %s => <info>Updated</info>',
                $orderIncrementId
            ));
        }

        if ($processedOrderCount > 0) {
            $output->writeLn(sprintf('<info>Processed Orders Count :</info> %s', $processedOrderCount));
        } else {
            $output->writeLn('<info>No order to update</info>');
        }
    }
}
