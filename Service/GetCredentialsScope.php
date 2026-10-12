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

    /**
     * @param ConfigDataCollectionFactory $configDataCollectionFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ConfigDataCollectionFactory $configDataCollectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Get the scope holding the Payplug credentials for a configuration scope: its website when set there,
     * else the default scope
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

        if ($websiteId && in_array($websiteId, $this->getWebsiteIdsWithCredentials(), true)) {
            return [ScopeInterface::SCOPE_WEBSITES, $websiteId];
        }

        return [ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0];
    }

    /**
     * Get the websites and store views inheriting the Payplug credentials held by the given scope
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
                $this->getWebsiteIdsWithCredentials()
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
     * Get the websites holding their own Payplug credentials (legacy or OAuth2), not inherited from default scope
     *
     * @return int[]
     */
    private function getWebsiteIdsWithCredentials(): array
    {
        $collection = $this->configDataCollectionFactory->create();
        $collection->addFieldToFilter('path', ['in' => self::CREDENTIALS_PATHS])
            ->addFieldToFilter('scope', ScopeInterface::SCOPE_WEBSITES)
            ->addFieldToFilter('value', ['neq' => '']);

        return array_values(array_unique(array_map('intval', $collection->getColumnValues('scope_id'))));
    }
}
