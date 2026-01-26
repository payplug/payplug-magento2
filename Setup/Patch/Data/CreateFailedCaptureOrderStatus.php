<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Setup\Patch\Data;

use Payplug\Payments\Api\Data\OrderInterface;
use Exception;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Status;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Store\Model\StoreManagerInterface;

class CreateFailedCaptureOrderStatus implements DataPatchInterface
{
    /**
     * Array of strings for the statuses
     */
    protected const STATUSES = [
        OrderInterface::FAILED_CAPTURE => [
            'default_label' => 'Failed Capture'
        ],
    ];

    /**
     * @param StatusFactory $statusFactory
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param ResourceConnection $resource
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        protected StatusFactory $statusFactory,
        protected ModuleDataSetupInterface $moduleDataSetup,
        protected ResourceConnection $resource,
        protected StoreManagerInterface $storeManager
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
     * Apply patch
     *
     * @return CreateFailedCaptureOrderStatus
     * @throws Exception
     */
    public function apply(): CreateFailedCaptureOrderStatus
    {
        /**
         * @var string $statusCode
         * @var string[] $label
         */
        foreach (self::STATUSES as $statusCode => $labels) {
            /** @var Status $status */
            $status = $this->statusFactory->create();
            $status->setData(
                [
                    'status' => $statusCode,
                    'label' => $labels['default_label'],
                ]
            );

            $status->save();

            /**
             * Assign status to state
             */
            $status->assignState(Order::STATE_PROCESSING, false, true);
        }

        return $this;
    }
}
