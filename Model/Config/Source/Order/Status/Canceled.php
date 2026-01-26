<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

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
