<?php

namespace Payplug\Payments\Plugin\Order;

use Magento\Sales\Model\Order;
use Payplug\Payments\Helper\Data;

class ModelPlugin
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @param Data $helper
     */
    public function __construct(Data $helper)
    {
        $this->helper = $helper;
    }

    /**
     * Override native canCancel return value
     *
     * @param Order $subject
     * @param bool  $result
     *
     * @return bool
     */
    public function afterCanCancel(Order $subject, $result): bool
    {
        return $result || $this->helper->canForceOrderCancel($subject);
    }

    /**
     * Cancel PayPlug payment before trying to cancel order
     *
     * @param Order $subject
     *
     * @return null
     */
    public function beforeCancel(Order $subject)
    {
        $this->helper->forceOrderCancel($subject);

        return null;
    }
}
