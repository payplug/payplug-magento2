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
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config;

class GetOauth2CompanyNameFromConfig
{
    private const EMAIL_PATH = Config::OAUTH_CONFIG_PATH . Config::OAUTH_EMAIL;
    private const COMPANY_NAME_PATH = Config::OAUTH_CONFIG_PATH . Config::OAUTH_COMPANY_NAME;

    /**
     * @param ConfigDataCollectionFactory $configDataCollectionFactory
     */
    public function __construct(
        private readonly ConfigDataCollectionFactory $configDataCollectionFactory
    ) {
    }

    /**
     * Get the company name stored in the scope that holds the Oauth2 email, without scope fallback
     *
     * @param int|null $websiteId
     * @return string|null
     */
    public function execute(?int $websiteId): ?string
    {
        $emailWebsiteId = $this->getEmailWebsiteId($websiteId);

        return $this->getValue(
            self::COMPANY_NAME_PATH,
            $emailWebsiteId ? ScopeInterface::SCOPE_WEBSITES : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            (int)$emailWebsiteId
        );
    }

    /**
     * Get the website holding its own Oauth2 email, null when the email comes from the default scope
     *
     * @param int|null $websiteId
     * @return int|null
     */
    public function getEmailWebsiteId(?int $websiteId): ?int
    {
        if ($websiteId && $this->getValue(self::EMAIL_PATH, ScopeInterface::SCOPE_WEBSITES, $websiteId) !== null) {
            return $websiteId;
        }

        return null;
    }

    /**
     * Get a value stored in the given scope, without scope fallback
     *
     * @param string $path
     * @param string $scope
     * @param int $scopeId
     * @return string|null
     */
    private function getValue(string $path, string $scope, int $scopeId): ?string
    {
        $collection = $this->configDataCollectionFactory->create();
        $collection->addFieldToFilter('path', $path)
            ->addFieldToFilter('scope', $scope)
            ->addFieldToFilter('scope_id', $scopeId);

        $item = $collection->getFirstItem();

        return $item->getId() ? (string)$item->getData('value') : null;
    }
}
