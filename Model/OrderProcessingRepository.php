<?php

namespace Payplug\Payments\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Payplug\Payments\Model\Order\Processing as OrderProcessing;
use Payplug\Payments\Model\Order\ProcessingFactory;
use Payplug\Payments\Model\ResourceModel\Order\Processing as ProcessingResource;

class OrderProcessingRepository
{
    /**
     * @var ProcessingResource
     */
    private $processingResource;

    /**
     * @var ProcessingFactory
     */
    private $processingFactory;

    /**
     * @param ProcessingResource $processingResource
     * @param ProcessingFactory  $processingFactory
     */
    public function __construct(ProcessingResource $processingResource, ProcessingFactory $processingFactory)
    {
        $this->processingResource = $processingResource;
        $this->processingFactory = $processingFactory;
    }

    /**
     * Get entity
     *
     * @param mixed       $id
     * @param string|null $field
     *
     * @return OrderProcessing
     *
     * @throws NoSuchEntityException
     */
    public function get($id, $field = null)
    {
        $object = $this->processingFactory->create();
        $this->processingResource->load($object, $id, $field);
        if (!$object->getId()) {
            throw new NoSuchEntityException(__('Object with id "%1" does not exist.', $id));
        }

        return $object;
    }

    /**
     * Save entity
     *
     * @param OrderProcessing $orderProcessing
     *
     * @return OrderProcessing
     */
    public function save(OrderProcessing $orderProcessing)
    {
        $this->processingResource->save($orderProcessing);

        return $orderProcessing;
    }

    /**
     * Delete entity
     *
     * @param OrderProcessing $orderProcessing
     *
     * @return bool
     */
    public function delete(OrderProcessing $orderProcessing)
    {
        $this->processingResource->delete($orderProcessing);

        return true;
    }
}
