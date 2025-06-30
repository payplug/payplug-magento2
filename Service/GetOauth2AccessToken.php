<?php

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface as ConfigWriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\JsonValidator;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Payplug\Authentication as PayplugAuthentication;
use Payplug\Payments\Helper\Config;

class GetOauth2AccessToken
{
    public function __construct(
        private readonly ReinitableConfigInterface $scopeConfig,
        private readonly JsonValidator $jsonValidator,
        private readonly SerializerInterface $serializer,
        private readonly ConfigWriterInterface $configWriter,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function execute(
        ?int $websiteId = null,
        bool $forceNenewal = false
    ): ?string {
        if ($forceNenewal === false) {
            $encryptedAccessTokenData = $this->getConfigValue(
                Config::OAUTH_CONFIG_PATH . Config::OAUTH_ACCESS_TOKEN_DATA,
                $websiteId
            );

            $serializedAccessTokenData = $this->encryptor->decrypt($encryptedAccessTokenData);

            if ($this->jsonValidator->isValid($serializedAccessTokenData) === false) {
                throw new LocalizedException(__('Access token data is not valid.'));
            }

            $accessTokenData = $this->serializer->unserialize($serializedAccessTokenData);
            $currentEnvMode = $this->getConfigValue(Config::CONFIG_PATH . Config::OAUTH_ENVIRONMENT_MODE, $websiteId);
            $tokenEnvMode = $accessTokenData['scope'];

            if ($currentEnvMode !== $tokenEnvMode) {
                throw new LocalizedException(__('Access token is not on the proper scope.'));
            }

            $expiredAt = $accessTokenData['expired_at'];
            $now = time();
            $tresholdBeforeExpiration = 10;

            if ($expiredAt > $now + $tresholdBeforeExpiration) {
                return (string)$accessTokenData['access_token'];
            }
        }

        $currentEnvMode = $this->getConfigValue(Config::CONFIG_PATH . Config::OAUTH_ENVIRONMENT_MODE, $websiteId);
        $encryptedClientDataValue = $this->getConfigValue(
            Config::OAUTH_CONFIG_PATH . Config::OAUTH_CLIENT_DATA,
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

        $value = $this->serializer->serialize($jwtResult['httpResponse']);
        $encryptedValue = $this->encryptor->encrypt($value);

        $this->configWriter->save(
            Config::OAUTH_CONFIG_PATH . Config::OAUTH_ACCESS_TOKEN_DATA,
            $encryptedValue,
            $websiteId ? StoreScopeInterface::SCOPE_WEBSITES : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $websiteId ?: 0
        );

        $this->scopeConfig->reinit();

        return (string)$jwtResult['httpResponse']['access_token'];
    }

    public function getConfigValue(string $path, ?int $websiteId = null)
    {
        return $this->scopeConfig->getValue(
            $path,
            $websiteId ? StoreScopeInterface::SCOPE_WEBSITES : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $websiteId ?: 0
        );
    }
}
