<?php

declare(strict_types=1);

namespace Payplug\Payments\Model\Config\Source\Order\Status;

use Magento\Sales\Model\Config\Source\Order\Status;
use Magento\Sales\Model\Order;

class Authorized extends Status
{
    /**
     * string[]
     */
    protected $_stateStatuses = [
        Order::STATE_PENDING_PAYMENT,
        Order::STATE_PROCESSING
    ];
}
