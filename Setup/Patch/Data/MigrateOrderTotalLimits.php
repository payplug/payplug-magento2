<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class MigrateOrderTotalLimits implements DataPatchInterface
{
    private const CONFIG_TABLE = 'core_config_data';
    private const RENAMED_PATHS = [
        'payment/payplug_payments_scalapay/min_threshold' =>
            'payment/payplug_payments_scalapay/min_order_total',
        'payment/payplug_payments_scalapay/max_threshold' =>
            'payment/payplug_payments_scalapay/max_order_total',
        'payment/payplug_payments_oney/oney_min_threshold' =>
            'payment/payplug_payments_oney/min_order_total',
        'payment/payplug_payments_oney/oney_max_threshold' =>
            'payment/payplug_payments_oney/max_order_total',
        'payment/payplug_payments_oney_without_fees/oney_min_threshold' =>
            'payment/payplug_payments_oney_without_fees/min_order_total',
        'payment/payplug_payments_oney_without_fees/oney_max_threshold' =>
            'payment/payplug_payments_oney_without_fees/max_order_total',
    ];
    private const REMOVED_PATHS = [
        'payment/payplug_payments_oney/oney_min_amounts',
        'payment/payplug_payments_oney/oney_max_amounts',
    ];

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    /**
     * Return dependencies
     *
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Get aliases
     *
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * Move the order total limits to the Magento native paths, in all scopes, and remove the obsolete Oney amounts
     *
     * @return MigrateOrderTotalLimits
     */
    public function apply(): MigrateOrderTotalLimits
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        $table = $this->moduleDataSetup->getTable(self::CONFIG_TABLE);

        foreach (self::RENAMED_PATHS as $oldPath => $newPath) {
            $overriddenIds = $connection->fetchCol(
                $connection->select()
                    ->from(['old' => $table], ['config_id'])
                    ->join(['new' => $table], 'new.scope = old.scope AND new.scope_id = old.scope_id', [])
                    ->where('old.path = ?', $oldPath)
                    ->where('new.path = ?', $newPath)
            );

            if ($overriddenIds) {
                $connection->delete($table, ['config_id IN (?)' => $overriddenIds]);
            }

            $connection->update($table, ['path' => $newPath], ['path = ?' => $oldPath]);
        }

        $connection->delete($table, ['path IN (?)' => self::REMOVED_PATHS]);
        $connection->endSetup();

        return $this;
    }
}
