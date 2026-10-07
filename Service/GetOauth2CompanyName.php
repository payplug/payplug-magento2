<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Service;

use InvalidArgumentException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Url\Decoder as UrlDecoder;
use Payplug\Core\APIRoutes;
use Payplug\Core\HttpClient;
use Payplug\Payments\Logger\Logger;
use Payplug\Payplug;
use Throwable;

class GetOauth2CompanyName
{
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
            $realmId = $this->getRealmId($accessToken);

            if ($realmId === '') {
                $this->logger->warning('Could not retrieve the Oauth2 company name: no realm_id in the access token');

                return '';
            }

            $httpClient = new HttpClient(new Payplug($accessToken));
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

            $this->logger->warning(sprintf(
                'Could not retrieve the Oauth2 company name: no name found for company %s in realm %s',
                $companyId,
                $realmId
            ));
        } catch (Throwable $e) {
            $this->logger->warning(sprintf('Could not retrieve the Oauth2 company name: %s', $e->getMessage()));
        }

        return '';
    }

    /**
     * Get the realm id from the JWT payload
     *
     * @param string $accessToken
     * @return string
     */
    private function getRealmId(string $accessToken): string
    {
        try {
            $payload = $this->json->unserialize($this->urlDecoder->decode(explode('.', $accessToken)[1] ?? ''));
        } catch (InvalidArgumentException) {
            $payload = null;
        }

        $realmId = $payload['realm_id'] ?? $payload['ext']['realm_id'] ?? null;

        return is_string($realmId) ? $realmId : '';
    }
}
