<?php

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
    public function __construct(
        private readonly ReinitableConfigInterface $scopeConfig,
        private readonly JsonValidator $jsonValidator,
        private readonly SerializerInterface $serializer,
        private readonly EncryptorInterface $encryptor
    ) {
    }

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
