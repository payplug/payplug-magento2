<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Service;

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
     * @return string
     * @throws LocalizedException
     */
    public function execute(array $params, string $separator): string
    {
        $hostedFieldsApiKey = $this->payplugConfig->getHostedFieldsApiKey() ?: null;
        $hostedFieldsAccountKey = $this->payplugConfig->getHostedFieldsAccountKey() ?: null;

        $separatorString = match ($separator) {
            self::SEPARATOR_API_KEY => $hostedFieldsApiKey,
            self::SEPARATOR_ACCOUNT_KEY  => $hostedFieldsAccountKey,
            default   => throw new LocalizedException(__('Invalid method')),
        };

        if ($separatorString === null) {
            throw new LocalizedException(__('Hosted Fields API key or Account Key is not set'));
        }

        ksort($params);

        $keyValues = [];

        foreach ($params as $k => $v) {
            $keyValues[] = "$k=$v";
        }

        $finalString = $separatorString . implode($separatorString, $keyValues) . $separatorString;

        return hash(self::HASH_ALGORITHM, $finalString);
    }
}
