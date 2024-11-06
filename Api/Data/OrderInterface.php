<?php

declare(strict_types=1);

namespace Payplug\Payments\Api\Data;

use Magento\Sales\Api\Data\OrderInterface as BaseOrderInterface;

interface OrderInterface extends BaseOrderInterface
{
    /**
     * This status is used when a payment capture fail, before invoice creation.
     * This status is associated to the pending_payment state
     */
    public const FAILED_CAPTURE = 'failed_capture';
}
