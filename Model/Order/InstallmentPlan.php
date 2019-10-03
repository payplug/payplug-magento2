<?php

namespace Payplug\Payments\Model\Order;

use Payplug\Payments\Helper\Config;

class InstallmentPlan extends \Magento\Framework\Model\AbstractModel implements
    \Magento\Framework\DataObject\IdentityInterface
{
    const CACHE_TAG = 'payplug_payments_order_installment_plan';

    const ORDER_ID = 'order_id';

    const INSTALLMENT_PLAN_ID = 'installment_plan_id';

    const IS_SANDBOX = 'is_sandbox';

    const STATUS = 'status';

    const STATUS_NEW = 0;
    const STATUS_ONGOING = 10;
    const STATUS_ABORTED = 20;
    const STATUS_COMPLETE = 30;

    /**
     * @var Config
     */
    private $payplugConfig;

    /**
     * @param \Magento\Framework\Model\Context                        $context
     * @param \Magento\Framework\Registry                             $registry
     * @param Config                                                  $payplugConfig
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb           $resourceCollection
     * @param array                                                   $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        Config $payplugConfig,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
        $this->payplugConfig = $payplugConfig;
    }

    protected function _construct()
    {
        $this->_init(\Payplug\Payments\Model\ResourceModel\Order\InstallmentPlan::class);
    }

    /**
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * @return int
     */
    public function getOrderId()
    {
        return $this->_getData(self::ORDER_ID);
    }

    /**
     * @param int $orderId
     *
     * @return $this
     */
    public function setOrderId($orderId)
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * @return string
     */
    public function getInstallmentPlanId()
    {
        return $this->_getData(self::INSTALLMENT_PLAN_ID);
    }

    /**
     * @param string $installmentPlanId
     *
     * @return $this
     */
    public function setInstallmentPlanId($installmentPlanId)
    {
        return $this->setData(self::INSTALLMENT_PLAN_ID, $installmentPlanId);
    }

    /**
     * @return bool
     */
    public function isSandbox()
    {
        return (bool) $this->_getData(self::IS_SANDBOX);
    }

    /**
     * @param bool $isSandbox
     *
     * @return $this
     */
    public function setIsSandbox($isSandbox)
    {
        return $this->setData(self::IS_SANDBOX, $isSandbox);
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->_getData(self::STATUS);
    }

    /**
     * @param int $status
     *
     * @return $this
     */
    public function setStatus($status)
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * Retrive an installment plan
     *
     * @param int|null $store
     *
     * @return \Payplug\Resource\InstallmentPlan
     */
    public function retrieve($store = null)
    {
        $this->payplugConfig->setPayplugApiKey($store, $this->isSandbox());

        return \Payplug\InstallmentPlan::retrieve($this->getInstallmentPlanId());
    }

    /**
     * Abort an installment plan
     *
     * @param int|null $store
     *
     * @return \Payplug\Resource\InstallmentPlan
     */
    public function abort($store = null)
    {
        $this->payplugConfig->setPayplugApiKey($store, $this->isSandbox());

        return \Payplug\InstallmentPlan::abort($this->getInstallmentPlanId());
    }
}
