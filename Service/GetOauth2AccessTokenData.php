<?php

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\JsonValidator;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Payplug\Authentication as PayplugAuthentication;
use Payplug\Payments\Helper\Config as ConfigHelper;

class GetOauth2AccessTokenData
{
    private const string CACHE_KEY = 'payplug_oauth2_access_token_data';
    private const int EXPIRATION_THRESHOLD = 10;

    public function __construct(
        private readonly ReinitableConfigInterface $scopeConfig,
        private readonly JsonValidator $jsonValidator,
        private readonly SerializerInterface $serializer,
        private readonly EncryptorInterface $encryptor,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function execute(
        ?int $websiteId = null,
        bool $forceNenewal = false
    ): ?array {
        $encryptedAccessTokenData = $this->cache->load(self::CACHE_KEY);

        if ($forceNenewal === true || !$encryptedAccessTokenData) {
            return $this->regenerate($websiteId);
        }

        $serializedAccessTokenData = $this->encryptor->decrypt($encryptedAccessTokenData);

        if ($this->jsonValidator->isValid($serializedAccessTokenData) === false) {
            throw new LocalizedException(__('Access token data is not valid.'));
        }

        $accessTokenData = $this->serializer->unserialize($serializedAccessTokenData);
        $currentEnvMode = $this->getCurrentEnvMode($websiteId);
        $tokenEnvMode = $accessTokenData['scope'];

        if ($currentEnvMode !== $tokenEnvMode) {
            throw new LocalizedException(__('Access token is not on the proper scope.'));
        }

        return $accessTokenData;
    }

    /**
     * @throws LocalizedException
     */
    private function regenerate(?int $websiteId = null): array
    {
        $currentEnvMode = $this->getCurrentEnvMode($websiteId);
        $encryptedClientDataValue = $this->getConfigValue(
            ConfigHelper::OAUTH_CONFIG_PATH . ConfigHelper::OAUTH_CLIENT_DATA,
            $websiteId
        );

        $serializedClientDataValue = $this->encryptor->decrypt($encryptedClientDataValue);

        if ($this->jsonValidator->isValid($serializedClientDataValue) === false) {
            throw new LocalizedException(__('Client data is not valid.'));
        }

        $clientData = $this->serializer->unserialize($serializedClientDataValue);
        $clientId = $clientData[$currentEnvMode]['client_id'];
        $clientSecret = $clientData[$currentEnvMode]['client_secret'];

        $jwtResult = PayplugAuthentication::generateJWT($clientId, $clientSecret);

        if (!$jwtResult || empty($jwtResult['httpResponse'])) {
            throw new LocalizedException(__('The JWT is invalid.'));
        }

        $now = time();
        $validityPeriod = (int)$jwtResult['httpResponse']['expires_in'];
        $jwtResult['httpResponse']['created_at'] = $now;
        $jwtResult['httpResponse']['expired_at'] = $now + $validityPeriod;

        $newAccessTokenData = $jwtResult['httpResponse'];

        $value = $this->serializer->serialize($newAccessTokenData);
        $encryptedValue = $this->encryptor->encrypt($value);
        $ttl = $validityPeriod - self::EXPIRATION_THRESHOLD;

        $this->cache->save($encryptedValue, self::CACHE_KEY, [], $ttl);

        return $newAccessTokenData;
    }

    private function getConfigValue(string $path, ?int $websiteId = null)
    {
        return $this->scopeConfig->getValue(
            $path,
            $websiteId ? StoreScopeInterface::SCOPE_WEBSITES : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $websiteId ?: 0
        );
    }

    private function getCurrentEnvMode(?int $websiteId = null)
    {
        return $this->getConfigValue(
            ConfigHelper::CONFIG_PATH . ConfigHelper::OAUTH_ENVIRONMENT_MODE,
            $websiteId
        );
    }
}
