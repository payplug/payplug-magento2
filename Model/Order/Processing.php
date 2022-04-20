<?php

namespace Payplug\Payments\Model\Order;

class Processing extends \Magento\Framework\Model\AbstractModel implements
    \Magento\Framework\DataObject\IdentityInterface
{
    public const CACHE_TAG = 'payplug_payments_order_processing';

    public const ORDER_ID = 'order_id';

    public const CREATED_AT = 'created_at';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(\Payplug\Payments\Model\ResourceModel\Order\Processing::class);
    }

    /**
     * Get entity identities
     *
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * Get order id
     *
     * @return int
     */
    public function getOrderId()
    {
        return $this->_getData(self::ORDER_ID);
    }

    /**
     * Set order id
     *
     * @param int $orderId
     *
     * @return $this
     */
    public function setOrderId($orderId)
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * Get created at
     *
     * @return string
     */
    public function getCreatedAt()
    {
        return $this->_getData(self::CREATED_AT);
    }

    /**
     * Set created at
     *
     * @param string $createdAt
     *
     * @return $this
     */
    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }
}
