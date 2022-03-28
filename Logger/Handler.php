<?php

namespace Payplug\Payments\Logger;

use Monolog\Logger;

class Handler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * Log File
     * @var string
     */
    protected $fileName = '/var/log/payplug_payments.log';
}
