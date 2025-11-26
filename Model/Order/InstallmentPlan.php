<?php

namespace Payplug\Payments\Model\Order;

use Magento\Framework\App\ScopeInterface as ScopeInterfaceDefault;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config;

class InstallmentPlan extends AbstractModel implements IdentityInterface
{
    public const CACHE_TAG = 'payplug_payments_order_installment_plan';

    public const ORDER_ID = 'order_id';

    public const INSTALLMENT_PLAN_ID = 'installment_plan_id';

    public const IS_SANDBOX = 'is_sandbox';

    public const STATUS = 'status';

    public const STATUS_NEW = 0;
    public const STATUS_ONGOING = 10;
    public const STATUS_ABORTED = 20;
    public const STATUS_COMPLETE = 30;

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
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
        $this->payplugConfig = $payplugConfig;
    }

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(\Payplug\Payments\Model\ResourceModel\Order\InstallmentPlan::class);
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
     * Get installment plan id
     *
     * @return string
     */
    public function getInstallmentPlanId()
    {
        return $this->_getData(self::INSTALLMENT_PLAN_ID);
    }

    /**
     * Set installement plan id
     *
     * @param string $installmentPlanId
     *
     * @return $this
     */
    public function setInstallmentPlanId($installmentPlanId)
    {
        return $this->setData(self::INSTALLMENT_PLAN_ID, $installmentPlanId);
    }

    /**
     * Get is sandbox
     *
     * @return bool
     */
    public function isSandbox()
    {
        return (bool) $this->_getData(self::IS_SANDBOX);
    }

    /**
     * Set is sandbox
     *
     * @param bool $isSandbox
     *
     * @return $this
     */
    public function setIsSandbox($isSandbox)
    {
        return $this->setData(self::IS_SANDBOX, $isSandbox);
    }

    /**
     * Get status
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->_getData(self::STATUS);
    }

    /**
     * Set status
     *
     * @param int $status
     *
     * @return $this
     */
    public function setStatus($status)
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getScope(OrderInterface $order): string
    {
        if ($order->getStoreId()) {
            return ScopeInterface::SCOPE_STORES;
        } elseif ($order->getStore()->getWebsiteId()) {
            return ScopeInterface::SCOPE_WEBSITES;
        }

        return ScopeInterfaceDefault::SCOPE_DEFAULT;
    }

    public function getScopeId(OrderInterface $order): int
    {
        if ($order->getStoreId()) {
            // Use store ID if non-zero
            return (int)$order->getStoreId();
        } elseif ($order->getStore()->getWebsiteId()) {
            // Otherwise use website ID if store ID == 0
            return (int)$order->getStore()->getWebsiteId();
        }
        // Otherwise default scope ID = 0
        return 0;
    }

    /**
     * Retrive an installment plan
     *
     * @param int|null $store
     *
     * @return \Payplug\Resource\InstallmentPlan
     */
    public function retrieve($store = null, ?string $scope = ScopeInterface::SCOPE_STORE)
    {
        $this->payplugConfig->setPayplugApiKey((int)$store, $this->isSandbox(), $scope);

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
        $this->payplugConfig->setPayplugApiKey((int)$store, $this->isSandbox());

        return \Payplug\InstallmentPlan::abort($this->getInstallmentPlanId());
    }
}
