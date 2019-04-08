<?php

namespace Payplug\Payments\Model\ResourceModel\Order\InstallmentPlan;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            'Payplug\Payments\Model\Order\InstallmentPlan',
            'Payplug\Payments\Model\ResourceModel\Order\InstallmentPlan'
        );
    }
}
