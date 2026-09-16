<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Url\Decoder as UrlDecoder;
use Payplug\Core\APIRoutes;
use Payplug\Core\HttpClient;
use Payplug\Exception\ConnectionException;
use Payplug\Exception\HttpException;
use Payplug\Exception\UnexpectedAPIResponseException;
use Payplug\Payments\Logger\Logger;
use Payplug\Payplug;
use Throwable;

class GetOauth2CompanyName
{
    private const SESSION_RESOURCE = '/users/api/v1/sessions/current';
    private const COMPANY_RESOURCE = '/users/api/v1/companies/';
    private const COMPANY_NAME_FIELDS = ['company_display_name', 'company_name'];

    /**
     * @param UrlDecoder $urlDecoder
     * @param Json $json
     * @param Logger $logger
     */
    public function __construct(
        private readonly UrlDecoder $urlDecoder,
        private readonly Json $json,
        private readonly Logger $logger
    ) {
    }

    /**
     * Retrieve the merchant company name from the Payplug users API
     *
     * @param string $accessToken
     * @param string $companyId
     * @return string
     */
    public function execute(string $accessToken, string $companyId): string
    {
        if ($accessToken === '' || $companyId === '') {
            return '';
        }

        try {
            $httpClient = new HttpClient(new Payplug($accessToken));
            $realmId = $this->getRealmId($httpClient, $accessToken);

            if ($realmId === '') {
                return '';
            }

            $response = $httpClient->get(
                APIRoutes::getServiceRoute(self::COMPANY_RESOURCE, ['realm_id' => $realmId])
            );
            $companies = is_array($response['httpResponse'] ?? null) ? $response['httpResponse'] : [];

            foreach ($companies as $company) {
                if (!is_array($company) || ($company['company_ref'] ?? null) !== $companyId) {
                    continue;
                }

                foreach (self::COMPANY_NAME_FIELDS as $field) {
                    $name = is_string($company[$field] ?? null) ? trim($company[$field]) : '';

                    if ($name !== '') {
                        return $name;
                    }
                }
            }
        } catch (Throwable $e) {
            $this->logger->info(sprintf('Could not retrieve the Oauth2 company name: %s', $e->getMessage()));
        }

        return '';
    }

    /**
     * Get the realm id from the JWT payload, or from the current session as a fallback
     *
     * @param HttpClient $httpClient
     * @param string $accessToken
     * @return string
     * @throws ConnectionException
     * @throws HttpException
     * @throws UnexpectedAPIResponseException
     */
    private function getRealmId(HttpClient $httpClient, string $accessToken): string
    {
        $payload = $this->json->unserialize($this->urlDecoder->decode(explode('.', $accessToken)[1] ?? ''));
        $realmId = $payload['realm_id'] ?? $payload['ext']['realm_id'] ?? null;

        if (!is_string($realmId) || $realmId === '') {
            $session = $httpClient->get(APIRoutes::getServiceRoute(self::SESSION_RESOURCE));
            $realmId = $session['httpResponse']['realmId'] ?? $session['httpResponse']['realm_ref'] ?? null;
        }

        return is_string($realmId) ? $realmId : '';
    }
}
