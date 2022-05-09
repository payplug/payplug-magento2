<?php

namespace Payplug\Payments\Model\ResourceModel\Order;

class Processing extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('payplug_payments_order_processing', 'entity_id');
    }
}
