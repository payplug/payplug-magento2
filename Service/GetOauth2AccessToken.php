<?php

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface as ConfigWriterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\JsonValidator;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Payplug\Authentication as PayplugAuthentication;

class GetOauth2AccessToken
{
    public function __construct(
        private readonly ReinitableConfigInterface $scopeConfig,
        private readonly JsonValidator $jsonValidator,
        private readonly SerializerInterface $serializer,
        private readonly ConfigWriterInterface $configWriter
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function execute(
        ?int $websiteId,
        bool $forceNenewal = false,
        ?string $clientId = null,
        ?string $clientSecret = null
    ): ?string {
        if ($forceNenewal === false) {
            $serializedAccessTokenData = $this->getConfigValue('payplug_payments/oauth2/access_token_data', $websiteId);
            if ($this->jsonValidator->isValid($serializedAccessTokenData) === false) {
                throw new LocalizedException(__('Access token data is not valid.'));
            }

            $accessTokenData = $this->serializer->unserialize($serializedAccessTokenData);

            $expiredAt = $accessTokenData['access_token'];
            $now = time();
            $tresholdBeforeExpiration = 10;

            if ($expiredAt > $now + $tresholdBeforeExpiration) {
                return (string)$accessTokenData['access_token'];
            }
        }

        if (!$clientId || !$clientSecret) {
            $currentEnvMode = $this->getConfigValue('payplug_payments/general/environmentmode', $websiteId);
            $serializedClientData = $this->getConfigValue('payplug_payments/oauth2/client_data', $websiteId);

            if ($this->jsonValidator->isValid($serializedClientData) === false) {
                throw new LocalizedException(__('Client data is not valid.'));
            }

            $clientData = $this->serializer->unserialize($serializedClientData);

            $clientId = $clientData[$currentEnvMode]['client_id'];
            $clientSecret = $clientData[$currentEnvMode]['client_secret'];
        }

        $jwtResult = PayplugAuthentication::generateJWT($clientId, $clientSecret);

        if (!$jwtResult || empty($jwtResult['httpResponse'])) {
            throw new LocalizedException(__('The JWT is invalid.'));
        }

        $now = time();
        $validityPeriod = (int)$jwtResult['httpResponse']['expires_in'];
        $jwtResult['httpResponse']['created_at'] = $now;
        $jwtResult['httpResponse']['expired_at'] = $now + $validityPeriod;

        $this->configWriter->save(
            'payplug_payments/oauth2/access_token_data',
            $this->serializer->serialize($jwtResult['httpResponse']),
            $websiteId ? StoreScopeInterface::SCOPE_WEBSITES : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $websiteId ?: 0
        );

        $this->scopeConfig->reinit();

        return $jwtResult['httpResponse']['access_token'];
    }

    private function getConfigValue(string $path, ?int $websiteId)
    {
        return $this->scopeConfig->getValue(
            $path,
            $websiteId ? StoreScopeInterface::SCOPE_WEBSITES : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $websiteId ?: 0
        );
    }
}
