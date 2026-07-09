<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Payplug\Payments\Helper\Config;

class BuildHostedFieldsParamsHash
{
    private const HASH_ALGORITHM = 'sha256';
    public const SEPARATOR_API_KEY = 'api_key';
    public const SEPARATOR_ACCOUNT_KEY = 'account_key';

    /**
     * @param Config $payplugConfig
     */
    public function __construct(
        private readonly Config $payplugConfig
    ) {
    }

    /**
     * Build the hash to be sent to Dalenys API
     *
     * @param array $params
     * @param string $separator
     * @param int $websiteId
     * @return string
     * @throws Exception
     */
    public function execute(array $params, string $separator, int $websiteId): string
    {
        $hostedFieldsApiKey = $this->payplugConfig->getHostedFieldsApiKey($websiteId) ?: null;
        $hostedFieldsAccountKey = $this->payplugConfig->getHostedFieldsAccountKey($websiteId) ?: null;

        if (empty($hostedFieldsApiKey) || empty($hostedFieldsAccountKey)) {
            throw new Exception('Hosted Fields API key or Account Key is not set');
        }

        $separatorString = match ($separator) {
            self::SEPARATOR_API_KEY => $hostedFieldsApiKey,
            self::SEPARATOR_ACCOUNT_KEY  => $hostedFieldsAccountKey,
            default => throw new Exception('No applicable separator found'),
        };

        ksort($params);

        $keyValues = [];

        foreach ($params as $k => $v) {
            $keyValues[] = "$k=$v";
        }

        $finalString = $separatorString . implode($separatorString, $keyValues) . $separatorString;

        return hash(self::HASH_ALGORITHM, $finalString);
    }
}
