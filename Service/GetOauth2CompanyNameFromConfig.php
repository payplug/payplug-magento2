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
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ConfigDataCollectionFactory $configDataCollectionFactory,
        private readonly ScopeConfigInterface $scopeConfig
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
        if (!$websiteId) {
            return $this->scopeConfig->getValue(self::COMPANY_NAME_PATH);
        }

        $collection = $this->configDataCollectionFactory->create();
        $collection->addFieldToFilter('path', ['in' => [self::EMAIL_PATH, self::COMPANY_NAME_PATH]])
            ->addFieldToFilter('scope', ScopeInterface::SCOPE_WEBSITES)
            ->addFieldToFilter('scope_id', $websiteId);

        $values = [];

        foreach ($collection as $item) {
            $values[$item->getData('path')] = $item->getData('value');
        }

        if (!array_key_exists(self::EMAIL_PATH, $values)) {
            return $this->scopeConfig->getValue(self::COMPANY_NAME_PATH);
        }

        return $values[self::COMPANY_NAME_PATH] ?? null;
    }
}
