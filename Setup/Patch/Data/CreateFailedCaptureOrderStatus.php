<?php

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

    public function __construct(
        protected StatusFactory $statusFactory,
        protected ModuleDataSetupInterface $moduleDataSetup,
        protected ResourceConnection $resource,
        protected StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
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
