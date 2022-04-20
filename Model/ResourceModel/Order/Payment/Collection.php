<?php

namespace Payplug\Payments\Model\ResourceModel\Order\Payment;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(
            \Payplug\Payments\Model\Order\Payment::class,
            \Payplug\Payments\Model\ResourceModel\Order\Payment::class
        );
    }
}
