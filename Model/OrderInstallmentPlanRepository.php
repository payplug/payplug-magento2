<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Payplug\Payments\Model\Order\InstallmentPlanFactory;
use Payplug\Payments\Model\Order\InstallmentPlan as OrderInstallmentPlan;
use Payplug\Payments\Model\ResourceModel\Order\InstallmentPlan as InstallmentPlanResource;

class OrderInstallmentPlanRepository
{
    /**
     * @var InstallmentPlanResource
     */
    private $installmentPlanResource;

    /**
     * @var InstallmentPlanFactory
     */
    private $installmentPlanFactory;

    /**
     * @param InstallmentPlanResource $installmentPlanResource
     * @param InstallmentPlanFactory  $installmentPlanFactory
     */
    public function __construct(
        InstallmentPlanResource $installmentPlanResource,
        InstallmentPlanFactory $installmentPlanFactory
    ) {
        $this->installmentPlanResource = $installmentPlanResource;
        $this->installmentPlanFactory = $installmentPlanFactory;
    }

    /**
     * Get entity
     *
     * @param mixed       $id
     * @param string|null $field
     *
     * @return OrderInstallmentPlan
     *
     * @throws NoSuchEntityException
     */
    public function get($id, $field = null)
    {
        $object = $this->installmentPlanFactory->create();
        $this->installmentPlanResource->load($object, $id, $field);
        if (!$object->getId()) {
            throw new NoSuchEntityException(__('Object with id "%1" does not exist.', $id));
        }

        return $object;
    }

    /**
     * Save entity
     *
     * @param OrderInstallmentPlan $orderInstallmentPlan
     *
     * @return OrderInstallmentPlan
     */
    public function save(OrderInstallmentPlan $orderInstallmentPlan)
    {
        $this->installmentPlanResource->save($orderInstallmentPlan);

        return $orderInstallmentPlan;
    }
}
