<?php

namespace Payplug\Payments\Model\ResourceModel\Customer\Card;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected function _construct()
    {
        $this->_init('Payplug\Payments\Model\Customer\Card', 'Payplug\Payments\Model\ResourceModel\Customer\Card');
    }
}
