<?php

namespace Payplug\Payments\Model;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Exception\NoSuchEntityException;
use Payplug\Payments\Model\Customer\Card as CustomerCard;
use Payplug\Payments\Model\Customer\CardFactory;
use Payplug\Payments\Model\ResourceModel\Customer\Card as CardResource;
use Payplug\Payments\Model\ResourceModel\Customer\Card\CollectionFactory;

class CustomerCardRepository
{
    /**
     * @var CardResource
     */
    private $cardResource;

    /**
     * @var CardFactory
     */
    private $cardFactory;

    /**
     * @var SearchResultsInterfaceFactory
     */
    private $searchResultsFactory;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @param CardResource $cardResource
     * @param CardFactory  $cardFactory
     */
    public function __construct(CardResource $cardResource, CardFactory $cardFactory, SearchResultsInterfaceFactory $searchResultsFactory, CollectionFactory $collectionFactory)
    {
        $this->cardResource = $cardResource;
        $this->cardFactory = $cardFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionFactory    = $collectionFactory;
    }

    /**
     * @param mixed       $id
     * @param string|null $field
     *
     * @return CustomerCard
     *
     * @throws NoSuchEntityException
     */
    public function get($id, $field = null)
    {
        $object = $this->cardFactory->create();
        $this->cardResource->load($object, $id, $field);
        if (!$object->getId()) {
            throw new NoSuchEntityException(__('Object with id "%1" does not exist.', $id));
        }

        return $object;
    }

    /**
     * @param CustomerCard $customerCard
     *
     * @return CustomerCard
     */
    public function save(CustomerCard $customerCard)
    {
        $this->cardResource->save($customerCard);

        return $customerCard;
    }

    /**
     * @param CustomerCard $customerCard
     *
     * @return bool
     */
    public function delete(CustomerCard $customerCard)
    {
        $this->cardResource->delete($customerCard);

        return true;
    }

    /**
     * @param SearchCriteriaInterface $criteria
     *
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $criteria)
    {
        /** @var SearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);
        /** @var CardResource\Collection $collection */
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
