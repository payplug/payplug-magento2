<?php

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface as ConfigWriterInterface;
use Magento\Framework\Serialize\JsonValidator;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Payplug\Authentication as PayplugAuthentication;

class RenewOauth2AccessToken
{
    public function __construct(
        private readonly ReinitableConfigInterface $scopeConfig,
        private readonly JsonValidator $jsonValidator,
        private readonly SerializerInterface $serializer,
        private readonly ConfigWriterInterface $configWriter
    ) {
    }

    public function execute(
        ?int $websiteId,
        bool $force = false,
        ?string $clientId = null,
        ?string $clientSecret = null
    ): void {
        if ($force === false) {
            $willExpired = false; // TODO check current token validity
            return;
        }

        if (!$clientId || !$clientSecret) {
            $currentEnvMode = $this->getConfigValue('payplug_payments/general/environmentmode', $websiteId);
            $serializedClientData = $this->getConfigValue('payplug_payments/oauth2/client_data', $websiteId);

            if ($this->jsonValidator->isValid($serializedClientData) === false) {
                return;
            }

            $clientData = $this->serializer->unserialize($serializedClientData);

            $clientId = $clientData[$currentEnvMode]['client_id'];
            $clientSecret = $clientData[$currentEnvMode]['client_secret'];
        }

        $jwtResult = PayplugAuthentication::generateJWT($clientId, $clientSecret);

        $this->configWriter->save(
            'payplug_payments/oauth2/access_token_data',
            $this->serializer->serialize($jwtResult['httpResponse']),
            $websiteId ? StoreScopeInterface::SCOPE_WEBSITES : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $websiteId ?: 0
        );

        $this->scopeConfig->reinit();
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
