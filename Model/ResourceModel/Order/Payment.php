<?php

namespace Payplug\Payments\Model\ResourceModel\Order;

class Payment extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected function _construct()
    {
        $this->_init('payplug_payments_order_payment', 'entity_id');
    }
}
