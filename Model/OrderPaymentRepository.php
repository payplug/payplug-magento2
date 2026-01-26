<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Model;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Exception\NoSuchEntityException;
use Payplug\Payments\Model\Order\Payment as OrderPayment;
use Payplug\Payments\Model\Order\PaymentFactory;
use Payplug\Payments\Model\ResourceModel\Order\Payment as PaymentResource;
use Payplug\Payments\Model\ResourceModel\Order\Payment\CollectionFactory;

class OrderPaymentRepository
{
    /**
     * @var PaymentResource
     */
    private $paymentResource;

    /**
     * @var PaymentFactory
     */
    private $paymentFactory;

    /**
     * @var SearchResultsInterfaceFactory
     */
    private $searchResultsFactory;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @param PaymentResource               $paymentResource
     * @param PaymentFactory                $paymentFactory
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionFactory             $collectionFactory
     */
    public function __construct(
        PaymentResource $paymentResource,
        PaymentFactory $paymentFactory,
        SearchResultsInterfaceFactory $searchResultsFactory,
        CollectionFactory $collectionFactory
    ) {
        $this->paymentResource = $paymentResource;
        $this->paymentFactory = $paymentFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionFactory    = $collectionFactory;
    }

    /**
     * Get entity
     *
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
     * Create entity
     *
     * @return OrderPayment
     */
    public function create()
    {
        return $this->paymentFactory->create();
    }

    /**
     * Save entity
     *
     * @param OrderPayment $orderPayment
     *
     * @return OrderPayment
     */
    public function save(OrderPayment $orderPayment)
    {
        $this->paymentResource->save($orderPayment);

        return $orderPayment;
    }

    /**
     * Get entity list
     *
     * @param SearchCriteriaInterface $criteria
     *
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $criteria)
    {
        /** @var SearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);
        /** @var Payment\Collection $collection */
        $collection = $this->collectionFactory->create();
        foreach ($criteria->getFilterGroups() as $filterGroup) {
            $fields = [];
            $conditions = [];
            foreach ($filterGroup->getFilters() as $filter) {
                $condition = $filter->getConditionType() ? $filter->getConditionType() : 'eq';
                $fields[] = $filter->getField();
                $conditions[] = [$condition => $filter->getValue()];
            }
            if ($fields) {
                $collection->addFieldToFilter($fields, $conditions);
            }
        }
        $searchResults->setTotalCount($collection->getSize());
        $sortOrders = $criteria->getSortOrders();
        if ($sortOrders) {
            /** @var SortOrder $sortOrder */
            foreach ($sortOrders as $sortOrder) {
                $collection->addOrder(
                    $sortOrder->getField(),
                    ($sortOrder->getDirection() == SortOrder::SORT_ASC) ? 'ASC' : 'DESC'
                );
            }
        }
        $collection->setCurPage($criteria->getCurrentPage());
        $collection->setPageSize($criteria->getPageSize());
        $objects = [];
        foreach ($collection as $objectModel) {
            $objects[] = $objectModel;
        }
        $searchResults->setItems($objects);

        return $searchResults;
    }
}
