<?php

namespace Payplug\Payments\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Payplug\Payments\Model\Order\Payment as OrderPayment;
use Payplug\Payments\Model\Order\PaymentFactory;
use Payplug\Payments\Model\ResourceModel\Order\Payment as PaymentResource;

class OrderPaymentRepository
{
    /**
     * @param PaymentResource $paymentResource
     * @param PaymentFactory  $paymentFactory
     */
    public function __construct(PaymentResource $paymentResource, PaymentFactory $paymentFactory)
    {
        $this->paymentResource = $paymentResource;
        $this->paymentFactory = $paymentFactory;
    }

    /**
     * @param mixed       $id
     * @param string|null $field
     *
     * @return OrderPayment
     *
     * @throws NoSuchEntityException
     */
    public function get($id, $field = null)
    {
        $object = $this->paymentFactory->create();
        $this->paymentResource->load($object, $id, $field);
        if (!$object->getId()) {
            throw new NoSuchEntityException(__('Object with id "%1" does not exist.', $id));
        }

        return $object;
    }

    /**
     * @param OrderPayment $orderPayment
     *
     * @return OrderPayment
     */
    public function save(OrderPayment $orderPayment)
    {
        $this->paymentResource->save($orderPayment);

        return $orderPayment;
    }
}
