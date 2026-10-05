<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Model\Api\Login;

class SynchronizeAccountData
{
    public const LAST_UPDATE_FIELD = 'account_last_update';
    public const ONEY_MERCHANT_GUID_FIELD = 'oney_merchant_guid';
    public const ONEY_BUSINESS_CODES_FIELD = 'oney_business_codes';
    public const ONEY_SHOW_LEGAL_NOTICES_FIELD = 'oney_show_legal_notices';

    private const PERMISSIONS = [
        'use_live_mode',
        'can_save_cards',
        'can_create_installment_plan',
        'can_create_deferred_payment',
        'can_use_oney',
        'can_use_bancontact',
        'can_use_apple_pay',
        'can_use_amex',
        'can_use_integrated_payments',
    ];

    private const ADDITIONAL_PPRO_METHODS = [
        'satispay',
        'ideal',
        'mybank',
        'bizum',
        'wero',
        'scalapay',
    ];

    /**
     * @var string
     */
    private string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;

    /**
     * @var int
     */
    private int $scopeId = 0;

    /**
     * @param Login $login
     * @param Config $helper
     * @param DateTime $dateTime
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly Login $login,
        private readonly Config $helper,
        private readonly DateTime $dateTime,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Fetch account data from Payplug API and persist it in the given configuration scope
     *
     * @param string $apiKey
     * @param string $scope
     * @param int $scopeId
     * @return array Account permissions
     * @throws LocalizedException
     */
    public function execute(string $apiKey, string $scope, int $scopeId): array
    {
        $result = $this->login->getAccount($apiKey);

        if (!$result['status']) {
            throw new LocalizedException(__($result['message']));
        }

        $this->scope = $scope;
        $this->scopeId = $scopeId;

        return $this->persist($result['answer']);
    }

    /**
     * Persist account data in the configuration scope and return permissions
     *
     * @param array $jsonAnswer
     * @return array
     */
    private function persist(array $jsonAnswer): array
    {
        $configuration = [
            'currencies' => $this->getConfig('currencies'),
            'min_amounts' => $this->getConfig('min_amounts'),
            'max_amounts' => $this->getConfig('max_amounts'),
            'oney_countries' => $this->getConfig('oney_countries'),
            'oney_min_amounts' => $this->getConfig('oney_min_amounts'),
            'oney_max_amounts' => $this->getConfig('oney_max_amounts'),
            'merchand_country' => $this->getConfig('merchand_country'),
            self::ONEY_MERCHANT_GUID_FIELD => '',
            self::ONEY_BUSINESS_CODES_FIELD => '',
            self::ONEY_SHOW_LEGAL_NOTICES_FIELD => '',
        ];

        $accountOneyAmounts = ['min' => null, 'max' => null];

        if (isset($jsonAnswer['configuration'])) {
            if (!empty($jsonAnswer['configuration']['currencies'])) {
                $configuration['currencies'] = array_values($jsonAnswer['configuration']['currencies']);
            }
            if (!empty($jsonAnswer['configuration']['min_amounts'])) {
                $configuration['min_amounts'] = $this->processAmounts($jsonAnswer['configuration']['min_amounts']);
            }
            if (!empty($jsonAnswer['configuration']['max_amounts'])) {
                $configuration['max_amounts'] = $this->processAmounts($jsonAnswer['configuration']['max_amounts']);
            }
            if (!empty($jsonAnswer['country'])) {
                $configuration['merchand_country'] = $jsonAnswer['country'];
            }
        }

        $oneyData = array_merge(
            $jsonAnswer['configuration']['oney'] ?? [],
            $jsonAnswer['payment_methods']['oney'] ?? []
        );
        if (!empty($oneyData)) {
            if (isset($oneyData['allowed_countries']) && is_array($oneyData['allowed_countries'])) {
                $configuration['oney_countries'] = $this->serializer->serialize($oneyData['allowed_countries']);
            }
            if (!empty($oneyData['min_amounts'])) {
                $configuration['oney_min_amounts'] = $this->processAmounts($oneyData['min_amounts']);
                $accountOneyAmounts['min'] = $this->getEurAmount($configuration['oney_min_amounts']);
            }
            if (!empty($oneyData['max_amounts'])) {
                $configuration['oney_max_amounts'] = $this->processAmounts($oneyData['max_amounts']);
                $accountOneyAmounts['max'] = $this->getEurAmount($configuration['oney_max_amounts']);
            }
            $merchantData = $this->getOneyMerchantData($oneyData, (string) $configuration['merchand_country']);
            if (!empty($merchantData['merchant_guid'])) {
                $configuration[self::ONEY_MERCHANT_GUID_FIELD] = (string) $merchantData['merchant_guid'];
            }
            if (!empty($merchantData['oney_business_codes']) && is_array($merchantData['oney_business_codes'])) {
                $configuration[self::ONEY_BUSINESS_CODES_FIELD] = $this->serializer->serialize(
                    $merchantData['oney_business_codes']
                );
            }
            if (array_key_exists('show_legal_notices', $oneyData)) {
                $configuration[self::ONEY_SHOW_LEGAL_NOTICES_FIELD] = (int) (bool) $oneyData['show_legal_notices'];
            }
        }

        $currencies = implode(';', (array) $configuration['currencies']);
        $this->saveConfig('currencies', $currencies);
        $this->saveConfig('min_amounts', $configuration['min_amounts']);
        $this->saveConfig('max_amounts', $configuration['max_amounts']);
        $this->saveConfig('oney_countries', $configuration['oney_countries']);
        $this->saveConfig('oney_min_amounts', $configuration['oney_min_amounts']);
        $this->saveConfig('oney_max_amounts', $configuration['oney_max_amounts']);
        $this->saveConfig(self::ONEY_MERCHANT_GUID_FIELD, $configuration[self::ONEY_MERCHANT_GUID_FIELD]);
        $this->saveConfig(self::ONEY_BUSINESS_CODES_FIELD, $configuration[self::ONEY_BUSINESS_CODES_FIELD]);
        $this->saveConfig(self::ONEY_SHOW_LEGAL_NOTICES_FIELD, $configuration[self::ONEY_SHOW_LEGAL_NOTICES_FIELD]);

        $this->saveOneyThresholds($accountOneyAmounts['min'], $accountOneyAmounts['max']);

        $this->saveConfig('company_id', $jsonAnswer['id'] ?? '');
        $this->saveConfig('merchand_country', $configuration['merchand_country']);

        $jsonAnswer['permissions']['can_use_bancontact'] =
            $jsonAnswer['payment_methods']['bancontact']['enabled'] ?? false;
        $jsonAnswer['permissions']['can_use_apple_pay'] =
            $jsonAnswer['payment_methods']['apple_pay']['enabled'] ?? false;
        $jsonAnswer['permissions']['can_use_amex'] =
            $jsonAnswer['payment_methods']['american_express']['enabled'] ?? false;

        $permissions = self::PERMISSIONS;

        foreach (self::ADDITIONAL_PPRO_METHODS as $method) {
            $jsonAnswer['permissions']['can_use_' . $method] = $jsonAnswer['payment_methods'][$method]['enabled']
                ?? false;
            $permissions[] = 'can_use_' . $method;
            $this->saveConfig(
                $method . '_countries',
                $this->serializer->serialize($jsonAnswer['payment_methods'][$method]['allowed_countries'] ?? [])
            );
            $this->saveConfig(
                $method . '_min_amounts',
                $this->processAmounts($jsonAnswer['payment_methods'][$method]['min_amounts'] ?? [])
            );
            $this->saveConfig(
                $method . '_max_amounts',
                $this->processAmounts($jsonAnswer['payment_methods'][$method]['max_amounts'] ?? [])
            );
        }

        foreach ($permissions as $permission) {
            $this->saveConfig($permission, (int) ($jsonAnswer['permissions'][$permission] ?? 0));
        }

        $this->saveConfig(self::LAST_UPDATE_FIELD, $this->dateTime->gmtDate());

        return $jsonAnswer['permissions'];
    }

    /**
     * Get Oney merchant data (merchant_guid, oney_business_codes) for the merchant country
     *
     * @param array $oneyData
     * @param string $merchantCountry
     * @return array
     */
    private function getOneyMerchantData(array $oneyData, string $merchantCountry): array
    {
        $countriesMetadata = $oneyData['countries_metadata'] ?? [];

        if (!is_array($countriesMetadata) || empty($countriesMetadata)) {
            return $oneyData;
        }

        if (!empty($countriesMetadata[$merchantCountry]) && is_array($countriesMetadata[$merchantCountry])) {
            return $countriesMetadata[$merchantCountry];
        }

        $singleCountry = count($countriesMetadata) === 1 ? reset($countriesMetadata) : null;

        return is_array($singleCountry) ? $singleCountry : [];
    }

    /**
     * Initialize the thresholds of both Oney payment methods with the account amounts, then keep them in this range
     *
     * @param int|null $minAmount
     * @param int|null $maxAmount
     * @return void
     */
    private function saveOneyThresholds(?int $minAmount, ?int $maxAmount): void
    {
        if ($minAmount === null || $maxAmount === null) {
            return;
        }

        foreach (['min' => $minAmount, 'max' => $maxAmount] as $bound => $accountAmount) {
            $thresholdField = sprintf('oney_%s_threshold', $bound);

            foreach ([Config::ONEY_CONFIG_PATH, Config::ONEY_WITHOUT_FEES_CONFIG_PATH] as $path) {
                $threshold = $this->getConfig($thresholdField, $path);
                $thresholdAmount = is_numeric($threshold) ? (int) round((float) $threshold * 100) : null;
                $amount = $thresholdAmount === null
                    ? $accountAmount
                    : $this->clampAmount($thresholdAmount, $minAmount, $maxAmount);

                if ($amount !== $thresholdAmount) {
                    $this->saveConfig($thresholdField, $amount / 100, $path);
                }
            }

            $amountsField = sprintf('oney_%s_amounts', $bound);
            $effectiveAmount = $this->getEurAmount($this->getConfig($amountsField, Config::ONEY_CONFIG_PATH));

            if ($effectiveAmount === null) {
                continue;
            }

            $amount = $this->clampAmount($effectiveAmount, $minAmount, $maxAmount);
            if ($amount !== $effectiveAmount) {
                $this->saveConfig($amountsField, 'EUR:' . $amount, Config::ONEY_CONFIG_PATH);
            }
        }
    }

    /**
     * Keep an amount within the given range
     *
     * @param int $amount
     * @param int $min
     * @param int $max
     * @return int
     */
    private function clampAmount(int $amount, int $min, int $max): int
    {
        return max($min, min($amount, $max));
    }

    /**
     * Get the EUR amount in cents from an amounts configuration value (e.g. EUR:10000;GBP:9000)
     *
     * @param mixed $amounts
     * @return int|null
     */
    private function getEurAmount(mixed $amounts): ?int
    {
        if (!is_string($amounts) || !preg_match('/(?:^|;)EUR:(\d+)/', $amounts, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * Process min/max amounts
     *
     * @param array|null $amounts
     * @return string
     */
    private function processAmounts(?array $amounts): string
    {
        return implode(';', array_map(
            static fn ($currency, $amount): string => $currency . ':' . $amount,
            array_keys($amounts ?? []),
            $amounts ?? []
        ));
    }

    /**
     * Get config value in the configuration scope
     *
     * @param string $field
     * @param string $path
     * @return mixed
     */
    private function getConfig(string $field, string $path = Config::CONFIG_PATH): mixed
    {
        return $this->helper->getConfigValue($field, $this->scope, $this->scopeId, $path);
    }

    /**
     * Save config value in the configuration scope
     *
     * @param string $field
     * @param mixed $value
     * @param string $path
     * @return void
     */
    private function saveConfig(string $field, mixed $value, string $path = Config::CONFIG_PATH): void
    {
        $this->helper->setConfigValue($field, (string) $value, $this->scope, $this->scopeId, $path);
    }
}
