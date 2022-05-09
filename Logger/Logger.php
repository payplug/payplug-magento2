<?php

namespace Payplug\Payments\Logger;

class Logger extends \Monolog\Logger
{
    /**
     * Add record to log
     *
     * @param int   $level
     * @param mixed $message
     * @param array $context
     *
     * @return Boolean Whether the record has been processed
     */
    public function addRecord($level, $message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = var_export($message, true);
        }

        return parent::addRecord($level, $message, $context);
    }
}
