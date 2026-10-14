<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory as ConfigDataCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Payplug\Payments\Helper\Config;

class GetCredentialsScope
{
    private const CREDENTIALS_PATHS = [
        Config::CONFIG_PATH . 'email',
        Config::CONFIG_PATH . 'test_api_key',
        Config::CONFIG_PATH . 'live_api_key',
        Config::OAUTH_CONFIG_PATH . Config::OAUTH_EMAIL,
        Config::OAUTH_CONFIG_PATH . Config::OAUTH_CLIENT_DATA,
    ];

    private const ENVIRONMENT_MODE_PATH = Config::CONFIG_PATH . 'environmentmode';

    /**
     * @param ConfigDataCollectionFactory $configDataCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ConfigDataCollectionFactory $configDataCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Get the scope holding the Payplug account data for a configuration scope: its website when it holds
     * its own credentials or its own environment mode, else the default scope
     *
     * @param string $scope
     * @param int $scopeId
     * @return array{0: string, 1: int}
     * @throws NoSuchEntityException
     */
    public function execute(string $scope, int $scopeId): array
    {
        $websiteId = 0;
        if (in_array($scope, [ScopeInterface::SCOPE_WEBSITES, ScopeInterface::SCOPE_WEBSITE], true)) {
            $websiteId = $scopeId;
        }
        if (in_array($scope, [ScopeInterface::SCOPE_STORES, ScopeInterface::SCOPE_STORE], true)) {
            $websiteId = (int) $this->storeManager->getStore($scopeId)->getWebsiteId();
        }

        if ($websiteId && in_array($websiteId, $this->getAccountWebsiteIds(), true)) {
            return [ScopeInterface::SCOPE_WEBSITES, $websiteId];
        }

        return [ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0];
    }

    /**
     * Get the websites and store views inheriting the Payplug account data held by the given scope
     *
     * @param string $credentialsScope
     * @param int $credentialsScopeId
     * @return array<string, int[]>
     */
    public function getInheritingScopeIds(string $credentialsScope, int $credentialsScopeId): array
    {
        $websiteIds = [];
        $storeWebsiteIds = [$credentialsScopeId];

        if ($credentialsScope !== ScopeInterface::SCOPE_WEBSITES) {
            $websiteIds = array_values(array_diff(
                array_map('intval', array_keys($this->storeManager->getWebsites())),
                $this->getAccountWebsiteIds()
            ));
            $storeWebsiteIds = $websiteIds;
        }

        $storeIds = [];
        foreach ($this->storeManager->getStores() as $store) {
            if (in_array((int) $store->getWebsiteId(), $storeWebsiteIds, true)) {
                $storeIds[] = (int) $store->getId();
            }
        }

        return [
            ScopeInterface::SCOPE_WEBSITES => $websiteIds,
            ScopeInterface::SCOPE_STORES => $storeIds,
        ];
    }

    /**
     * Get the websites holding their own Payplug account data: their own credentials (legacy or OAuth2),
     * or their own environment mode different from the default one
     *
     * @return int[]
     */
    private function getAccountWebsiteIds(): array
    {
        $defaultEnvironmentMode = (string) $this->scopeConfig->getValue(self::ENVIRONMENT_MODE_PATH);

        $collection = $this->configDataCollectionFactory->create();
        $collection->addFieldToFilter('path', ['in' => [...self::CREDENTIALS_PATHS, self::ENVIRONMENT_MODE_PATH]])
            ->addFieldToFilter('scope', ScopeInterface::SCOPE_WEBSITES)
            ->addFieldToFilter('value', ['neq' => '']);

        $websiteIds = [];

        foreach ($collection as $item) {
            if ($item->getData('path') !== self::ENVIRONMENT_MODE_PATH
                || $item->getData('value') !== $defaultEnvironmentMode
            ) {
                $websiteIds[] = (int) $item->getData('scope_id');
            }
        }

        return array_values(array_unique($websiteIds));
    }
}
