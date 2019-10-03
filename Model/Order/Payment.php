<?php

namespace Payplug\Payments\Model\Order;

use Payplug\Payments\Helper\Config;

class Payment extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{
    const CACHE_TAG = 'payplug_payments_order_payment';

    const ORDER_ID = 'order_id';

    const PAYMENT_ID = 'payment_id';

    const IS_SANDBOX = 'is_sandbox';

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
        $this->_init(\Payplug\Payments\Model\ResourceModel\Order\Payment::class);
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
    public function getPaymentId()
    {
        return $this->_getData(self::PAYMENT_ID);
    }

    /**
     * @param string $paymentId
     *
     * @return $this
     */
    public function setPaymentId($paymentId)
    {
        return $this->setData(self::PAYMENT_ID, $paymentId);
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
     * Retrive a payment
     *
     * @param int|null $store
     *
     * @return \Payplug\Resource\Payment
     */
    public function retrieve($store = null)
    {
        $this->payplugConfig->setPayplugApiKey($store, $this->isSandbox());

        return \Payplug\Payment::retrieve($this->getPaymentId());
    }

    /**
     * Attempt to refund partially or totally a payment
     *
     * @param float    $amount
     * @param array    $metadata
     * @param int|null $store
     *
     * @return \Payplug\Resource\Refund
     */
    public function makeRefund($amount, $metadata, $store = null)
    {
        $data = [
            'amount' => $amount * 100,
            'metadata' => $metadata
        ];

        $this->payplugConfig->setPayplugApiKey($store, $this->isSandbox());

        return \Payplug\Refund::create($this->getPaymentId(), $data);
    }
}
