<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\JsonValidator;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Payplug\Payments\Helper\Config as ConfigHelper;

class GetOauth2ClientData
{
    /**
     * @param ReinitableConfigInterface $scopeConfig
     * @param JsonValidator $jsonValidator
     * @param SerializerInterface $serializer
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        private readonly ReinitableConfigInterface $scopeConfig,
        private readonly JsonValidator $jsonValidator,
        private readonly SerializerInterface $serializer,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    /**
     * Get the oauth client data from config
     *
     * @param string $currentEnvMode
     * @param int|null $websiteId
     * @return array|null
     */
    public function execute(string $currentEnvMode, ?int $websiteId = null): ?array
    {
        $encryptedClientDataValue = $this->scopeConfig->getValue(
            ConfigHelper::OAUTH_CONFIG_PATH . ConfigHelper::OAUTH_CLIENT_DATA,
            $websiteId ? StoreScopeInterface::SCOPE_WEBSITES : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $websiteId ?: 0
        );

        $serializedClientDataValue = $this->encryptor->decrypt($encryptedClientDataValue);

        if ($this->jsonValidator->isValid($serializedClientDataValue) === false) {
            return null;
        }

        $clientData = $this->serializer->unserialize($serializedClientDataValue);

        return $clientData[$currentEnvMode] ?? null;
    }
}
