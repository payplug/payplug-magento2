<?php

namespace Payplug\Payments\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * {@inheritdoc}
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;

        /**
         * Prepare database for install
         */
        $installer->startSetup();

        //START table setup
        $table = $installer->getConnection()->newTable($installer->getTable('payplug_payments_order_payment'))
            ->addColumn(
                'entity_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => true,'nullable' => false,'primary' => true,'unsigned' => true],
                'Entity ID'
            )
            ->addColumn(
                'order_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => false,'nullable' => false,'primary' => false,'unsigned' => true],
                'Order ID'
            )
            ->addColumn(
                'payment_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                null,
                ['nullable' => false, 'default' => 'pay_xxxxxxxxxxxxxxxxxxxxx'],
                'Payment ID'
            )
            ->addColumn(
                'is_sandbox',
                \Magento\Framework\DB\Ddl\Table::TYPE_BOOLEAN,
                null,
                ['nullable' => false, 'default' => 0],
                'Is Sandbox'
            )
            ->setComment('PayPlug Order Payment Table')
        ;

        $installer->getConnection()->createTable($table);

        /**
         * Prepare database after install
         */
        $installer->endSetup();
    }
}
