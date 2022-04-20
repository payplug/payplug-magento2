<?php

namespace Payplug\Payments\Helper;

use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\FilterBuilderFactory;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilderFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteriaInterfaceFactory;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Api\SortOrderBuilderFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Payplug\Payments\Model\CustomerCardRepository;

class Card extends AbstractHelper
{
    /**
     * @var CustomerCardRepository
     */
    private $customerCardRepository;

    /**
     * @var SearchCriteriaInterfaceFactory
     */
    private $searchCriteriaInterfaceFactory;

    /**
     * @var FilterBuilderFactory
     */
    private $filterBuilderFactory;

    /**
     * @var FilterGroupBuilderFactory
     */
    private $filterGroupBuilderFactory;

    /**
     * @var SortOrderBuilderFactory
     */
    private $sortOrderBuilderFactory;

    /**
     * @var Config
     */
    private $helper;

    /**
     * @param Context                        $context
     * @param CustomerCardRepository         $customerCardRepository
     * @param SearchCriteriaInterfaceFactory $searchCriteriaInterfaceFactory
     * @param FilterBuilderFactory           $filterBuilderFactory
     * @param FilterGroupBuilderFactory      $filterGroupBuilderFactory
     * @param SortOrderBuilderFactory        $sortOrderBuilderFactory
     * @param Config                         $helper
     */
    public function __construct(
        Context $context,
        CustomerCardRepository $customerCardRepository,
        SearchCriteriaInterfaceFactory $searchCriteriaInterfaceFactory,
        FilterBuilderFactory $filterBuilderFactory,
        FilterGroupBuilderFactory $filterGroupBuilderFactory,
        SortOrderBuilderFactory $sortOrderBuilderFactory,
        Config $helper
    ) {
        parent::__construct($context);
        $this->customerCardRepository = $customerCardRepository;
        $this->searchCriteriaInterfaceFactory = $searchCriteriaInterfaceFactory;
        $this->filterBuilderFactory = $filterBuilderFactory;
        $this->filterGroupBuilderFactory = $filterGroupBuilderFactory;
        $this->sortOrderBuilderFactory = $sortOrderBuilderFactory;
        $this->helper = $helper;
    }

    /**
     * Get customer last created card
     *
     * @param int $customerId
     *
     * @return int
     */
    public function getLastCardIdByCustomer($customerId)
    {
        /** @var SearchCriteriaInterface $criteria */
        $criteria = $this->searchCriteriaInterfaceFactory->create();

        $criteria->setFilterGroups([$this->getFieldFilter('customer_id', $customerId)]);

        /** @var SortOrder $sortOrder */
        /** @var SortOrderBuilder $sortBuilder */
        $sortBuilder = $this->sortOrderBuilderFactory->create();
        $sortBuilder->setField('customer_card_id');
        $sortBuilder->setDescendingDirection();
        $sortOrder = $sortBuilder->create();

        $criteria->setSortOrders([$sortOrder]);

        $criteria->setPageSize(1);

        $result = $this->customerCardRepository->getList($criteria);
        $cards = $result->getItems();

        $maxCardId = 0;
        if (!empty($cards)) {
            $maxCardId = $cards[0]->getCustomerCardId();
        }

        return $maxCardId;
    }

    /**
     * Get customer cards
     *
     * @param int  $customerId
     * @param bool $includeExpiredCards
     *
     * @return \Magento\Framework\Api\ExtensibleDataInterface[]|\Payplug\Payments\Model\Customer\Card[]
     */
    public function getCardsByCustomer($customerId, $includeExpiredCards = false)
    {
        /** @var SearchCriteriaInterface $criteria */
        $criteria = $this->searchCriteriaInterfaceFactory->create();

        $isSandbox = $this->helper->getIsSandbox();
        $companyId = $this->helper->getConfigValue('company_id');

        $filterGroups = [
            $this->getFieldFilter('customer_id', $customerId),
            $this->getFieldFilter('is_sandbox', $isSandbox),
            $this->getFieldFilter('company_id', $companyId),
        ];

        if (!$includeExpiredCards) {
            $currentDate = date('Y-m-d H:i:s');
            $filterGroups[] = $this->getFieldFilter('exp_date', $currentDate, 'gt');
        }

        $criteria->setFilterGroups($filterGroups);

        /** @var SortOrder $sortOrder */
        /** @var SortOrderBuilder $sortBuilder */
        $sortBuilder = $this->sortOrderBuilderFactory->create();
        $sortBuilder->setField('customer_card_id');
        $sortBuilder->setAscendingDirection();
        $sortOrder = $sortBuilder->create();

        $criteria->setSortOrders([$sortOrder]);

        $result = $this->customerCardRepository->getList($criteria);
        $cards = $result->getItems();

        return $cards;
    }

    /**
     * Build field filter for repository search
     *
     * @param string $field
     * @param mixed  $value
     * @param string $type
     *
     * @return FilterGroup
     */
    private function getFieldFilter($field, $value, $type = 'eq')
    {
        /** @var Filter $filter */
        /** @var FilterBuilder $filterBuilder */
        $filterBuilder = $this->filterBuilderFactory->create();
        $filterBuilder->setField($field);
        $filterBuilder->setConditionType($type);
        $filterBuilder->setValue($value);
        $filter = $filterBuilder->create();

        /** @var FilterGroup $filterGroup */
        /** @var FilterGroupBuilder $filterGroupBuilder */
        $filterGroupBuilder = $this->filterGroupBuilderFactory->create();
        $filterGroupBuilder->addFilter($filter);
        $filterGroup = $filterGroupBuilder->create();

        return $filterGroup;
    }

    /**
     * Format expiration date
     *
     * @param string $date
     *
     * @return string
     */
    public function getFormattedExpDate($date)
    {
        if (empty($date) || $date == '0000-00-00 00:00:00') {
            return $date;
        }

        return date('m/y', strtotime($date));
    }

    /**
     * Delete customer card
     *
     * @param int $customerId
     * @param int $customerCardId
     */
    public function deleteCustomerCard($customerId, $customerCardId)
    {
        $card = $this->getCustomerCard($customerId, $customerCardId);
        $this->customerCardRepository->delete($card);
    }

    /**
     * Get customer card
     *
     * @param int $customerId
     * @param int $customerCardId
     *
     * @return \Payplug\Payments\Model\Customer\Card
     *
     * @throws NoSuchEntityException
     */
    public function getCustomerCard($customerId, $customerCardId)
    {
        /** @var SearchCriteriaInterface $criteria */
        $criteria = $this->searchCriteriaInterfaceFactory->create();

        $isSandbox = $this->helper->getIsSandbox();
        $companyId = $this->helper->getConfigValue('company_id');
        $criteria->setFilterGroups([
            $this->getFieldFilter('customer_id', $customerId),
            $this->getFieldFilter('is_sandbox', $isSandbox),
            $this->getFieldFilter('company_id', $companyId),
            $this->getFieldFilter('customer_card_id', $customerCardId),
        ]);

        $criteria->setPageSize(1);

        $result = $this->customerCardRepository->getList($criteria);
        $cards = $result->getItems();

        if (count($cards) == 0) {
            throw new NoSuchEntityException(__('This card does not exist.'));
        }

        return $cards[0];
    }
}
