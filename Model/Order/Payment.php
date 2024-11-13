<?php

namespace Payplug\Payments\Model\Order;

use Payplug\Payments\Helper\Config;

class Payment extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{
    public const CACHE_TAG = 'payplug_payments_order_payment';

    public const ORDER_ID = 'order_id';

    public const PAYMENT_ID = 'payment_id';

    public const IS_SANDBOX = 'is_sandbox';

    public const IS_INSTALLMENT_PLAN_PAYMENT_PROCESSED = 'is_installment_plan_payment_processed';

    public const SENT_BY = 'sent_by';

    public const SENT_BY_VALUE = 'sent_by_value';

    public const LANGUAGE = 'language';

    public const DESCRIPTION = 'description';

    public const SENT_BY_SMS = 'SMS';
    public const SENT_BY_EMAIL = 'EMAIL';

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

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(\Payplug\Payments\Model\ResourceModel\Order\Payment::class);
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
     * Get payment id
     *
     * @return string
     */
    public function getPaymentId()
    {
        return $this->_getData(self::PAYMENT_ID);
    }

    /**
     * Set payment id
     *
     * @param string $paymentId
     *
     * @return $this
     */
    public function setPaymentId($paymentId)
    {
        return $this->setData(self::PAYMENT_ID, $paymentId);
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
     * Get installment plan processed flag
     *
     * @return bool
     */
    public function isInstallmentPlanPaymentProcessed()
    {
        return (bool) $this->_getData(self::IS_INSTALLMENT_PLAN_PAYMENT_PROCESSED);
    }

    /**
     * Set installment plan processed flag
     *
     * @param bool $isProcessed
     *
     * @return $this
     */
    public function setIsInstallmentPlanPaymentProcessed($isProcessed)
    {
        return $this->setData(self::IS_INSTALLMENT_PLAN_PAYMENT_PROCESSED, $isProcessed);
    }

    /**
     * Get sent by
     *
     * @return string|null
     */
    public function getSentBy()
    {
        return $this->_getData(self::SENT_BY);
    }

    /**
     * Set sent by
     *
     * @param string|null $sentBy
     *
     * @return $this
     */
    public function setSentBy($sentBy = null)
    {
        return $this->setData(self::SENT_BY, $sentBy);
    }

    /**
     * Get sent by value
     *
     * @return string|null
     */
    public function getSentByValue()
    {
        return $this->_getData(self::SENT_BY_VALUE);
    }

    /**
     * Set sent by value
     *
     * @param string|null $sentByValue
     *
     * @return $this
     */
    public function setSentByValue($sentByValue = null)
    {
        return $this->setData(self::SENT_BY_VALUE, $sentByValue);
    }

    /**
     * Get language
     *
     * @return string|null
     */
    public function getLanguage()
    {
        return $this->_getData(self::LANGUAGE);
    }

    /**
     * Set language
     *
     * @param string|null $language
     *
     * @return $this
     */
    public function setLanguage($language = null)
    {
        return $this->setData(self::LANGUAGE, $language);
    }

    /**
     * Get description
     *
     * @return string|null
     */
    public function getDescription()
    {
        return $this->_getData(self::DESCRIPTION);
    }

    /**
     * Set description
     *
     * @param string|null $description
     *
     * @return $this
     */
    public function setDescription($description = null)
    {
        return $this->setData(self::DESCRIPTION, $description);
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
        $this->payplugConfig->setPayplugApiKey((int)$store, $this->isSandbox());

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

        $this->payplugConfig->setPayplugApiKey((int)$store, $this->isSandbox());

        return \Payplug\Refund::create($this->getPaymentId(), $data);
    }

    /**
     * Abort an installment plan
     *
     * @param int|null $store
     *
     * @return \Payplug\Resource\Payment
     */
    public function abort($store = null)
    {
        $this->payplugConfig->setPayplugApiKey((int)$store, $this->isSandbox());

        return \Payplug\Payment::abort($this->getPaymentId());
    }

    /**
     * Update a payment
     *
     * @param array    $data
     * @param int|null $store
     *
     * @return \Payplug\Resource\Payment
     */
    public function update(array $data, $store = null)
    {
        $this->payplugConfig->setPayplugApiKey((int)$store, $this->isSandbox());

        $payment = \Payplug\Resource\Payment::fromAttributes(['id' => $this->getPaymentId()]);

        return $payment->update($data);
    }
}
