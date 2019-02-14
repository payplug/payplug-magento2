<?php

namespace Payplug\Payments\Model\ResourceModel\Order\Processing;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            'Payplug\Payments\Model\Order\Processing',
            'Payplug\Payments\Model\ResourceModel\Order\Processing'
        );
    }
}
