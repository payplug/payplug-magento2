<?php

namespace Payplug\Payments\Model\ResourceModel\Order;

class InstallmentPlan extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('payplug_payments_order_installment_plan', 'entity_id');
    }
}
