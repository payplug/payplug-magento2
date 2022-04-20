<?php

namespace Payplug\Payments\Model\ResourceModel\Customer;

class Card extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('payplug_payments_card', 'entity_id');
    }
}
