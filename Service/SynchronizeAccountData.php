<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\ScopeInterface;
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
     * Fetch account data from Payplug API and persist it in the current configuration scope
     *
     * The configuration scope must be initialized beforehand with Config::initScopeData()
     *
     * @param string $apiKey
     * @return array Account permissions
     * @throws LocalizedException
     */
    public function execute(string $apiKey): array
    {
        $result = $this->login->getAccount($apiKey);

        if (!$result['status']) {
            throw new LocalizedException(__($result['message']));
        }

        return $this->persist($result['answer']);
    }

    /**
     * Persist account data in the current configuration scope and return permissions
     *
     * @param array $jsonAnswer
     * @return array
     */
    public function persist(array $jsonAnswer): array
    {
        $configuration = [
            'currencies' => $this->getConfig('currencies'),
            'min_amounts' => $this->getConfig('min_amounts'),
            'max_amounts' => $this->getConfig('max_amounts'),
            'oney_countries' => $this->getConfig('oney_countries'),
            'oney_min_amounts' => $this->getConfig('oney_min_amounts'),
            'oney_max_amounts' => $this->getConfig('oney_max_amounts'),
            'merchand_country' => $this->getConfig('merchand_country'),
            self::ONEY_MERCHANT_GUID_FIELD => $this->getConfig(self::ONEY_MERCHANT_GUID_FIELD),
            self::ONEY_BUSINESS_CODES_FIELD => $this->getConfig(self::ONEY_BUSINESS_CODES_FIELD),
            self::ONEY_SHOW_LEGAL_NOTICES_FIELD => $this->getConfig(self::ONEY_SHOW_LEGAL_NOTICES_FIELD),
            'raw_oney_min_amounts' => null,
            'raw_oney_max_amounts' => null,
        ];

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
                $minAmounts = (int) ($oneyData['min_amounts']['EUR'] ?? 0);
                $configuration['raw_oney_min_amounts'] = $minAmounts / 100;
            }
            if (!empty($oneyData['max_amounts'])) {
                $configuration['oney_max_amounts'] = $this->processAmounts($oneyData['max_amounts']);
                $maxAmount = (int) ($oneyData['max_amounts']['EUR'] ?? 0);
                $configuration['raw_oney_max_amounts'] = $maxAmount / 100;
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

        $this->saveOneyThresholds($configuration['raw_oney_min_amounts'], $configuration['raw_oney_max_amounts']);

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
     * The API exposes them per country in countries_metadata; older payloads expose them at the oney object level.
     *
     * @param array $oneyData
     * @param string $merchantCountry
     * @return array
     */
    private function getOneyMerchantData(array $oneyData, string $merchantCountry): array
    {
        $countriesMetadata = $oneyData['countries_metadata'] ?? [];
        if (is_array($countriesMetadata) && !empty($countriesMetadata)) {
            if (!empty($countriesMetadata[$merchantCountry]) && is_array($countriesMetadata[$merchantCountry])) {
                return $countriesMetadata[$merchantCountry];
            }

            $firstCountry = reset($countriesMetadata);
            if (is_array($firstCountry)) {
                return $firstCountry;
            }
        }

        return $oneyData;
    }

    /**
     * Save Oney thresholds on both Oney payment methods
     *
     * @param float|null $min
     * @param float|null $max
     * @return void
     */
    private function saveOneyThresholds(?float $min, ?float $max): void
    {
        $scope = ScopeInterface::SCOPE_STORE;
        foreach ([Config::ONEY_CONFIG_PATH, Config::ONEY_WITHOUT_FEES_CONFIG_PATH] as $path) {
            $this->helper->setConfigValue('oney_min_threshold', (string) $min, $scope, null, $path);
            $this->helper->setConfigValue('oney_max_threshold', (string) $max, $scope, null, $path);
        }
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
     * Get config value in current scope
     *
     * @param string $field
     * @return mixed
     */
    private function getConfig(string $field): mixed
    {
        return $this->helper->getConfigValue($field, ScopeInterface::SCOPE_STORE, null, Config::CONFIG_PATH);
    }

    /**
     * Save config value in current scope
     *
     * @param string $field
     * @param mixed $value
     * @return void
     */
    private function saveConfig(string $field, mixed $value): void
    {
        $this->helper->setConfigValue($field, (string) $value);
    }
}
