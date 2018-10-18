<?php

namespace Payplug\Payments\Model\Config\Source\Order\Status;

use Magento\Sales\Model\Config\Source\Order\Status;
use Magento\Sales\Model\Order;

class Canceled extends Status
{
    /**
     * @var string
     */
    protected $_stateStatuses = Order::STATE_CANCELED;
}
