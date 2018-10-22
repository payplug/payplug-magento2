<?php

namespace Payplug\Payments\Model\Order;

use Payplug\Payments\Model\PaymentMethod;
use Payplug\Payplug;

class Payment extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{
    const CACHE_TAG = 'payplug_payments_order_payment';

    /**
     * @var PaymentMethod
     */
    protected $paymentMethod;

    /**
     * @param \Magento\Framework\Model\Context                        $context
     * @param \Magento\Framework\Registry                             $registry
     * @param PaymentMethod                                           $paymentMethod
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb           $resourceCollection
     * @param array                                                   $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        PaymentMethod $paymentMethod,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
        $this->paymentMethod = $paymentMethod;
    }

    protected function _construct()
    {
        $this->_init('Payplug\Payments\Model\ResourceModel\Order\Payment');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * Retrive a payment
     *
     * @param int         $paymentId
     * @param string|null $environmentMode
     * @param int|null    $store
     *
     * @return \Payplug\Resource\Payment
     */
    public function retrieve($paymentId, $environmentMode = null, $store = null)
    {
        $validKey = $this->paymentMethod->setAPIKey($store, $environmentMode);
        if ($validKey != null) {
            Payplug::setSecretKey($validKey);
        }

        return \Payplug\Payment::retrieve($paymentId);
    }
}
