<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Helper;

use Exception;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\Resolver;
use Magento\Payment\Helper\Data as PaymentDataHelper;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\Serialize\SerializerInterface;
use Payplug\Payments\Gateway\Config\Oney as OneyConfig;
use Payplug\Payments\Gateway\Config\OneyWithoutFees;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Service\GetAllowedCountriesPerPaymentMethod;
use Payplug\Payments\Service\SynchronizeAccountData;

class Oney extends AbstractHelper
{
    public const ALLOWED_OPERATIONS_BY_PAYMENT = [
        OneyConfig::METHOD_CODE => [
            'x3_with_fees' => '3x',
            'x4_with_fees' => '4x',
        ],
        OneyWithoutFees::METHOD_CODE => [
            'x3_without_fees' => '3x',
            'x4_without_fees' => '4x',
        ],
    ];

    public const MAX_ITEMS = 1000;

    public const WIDGET_LOADER_URL_LIVE = 'https://assets.oney.io/build/loader.min.js';
    public const WIDGET_LOADER_URL_TEST = 'https://assets-uat.oney.io/build/loader.min.js';

    /**
     * @var string|null
     */
    private ?string $oneyMethod = null;

    /**
     * @param Context $context
     * @param Config $payplugConfig
     * @param StoreManagerInterface $storeManager
     * @param PricingHelper $pricingHelper
     * @param CountryFactory $countryFactory
     * @param Resolver $localeResolver
     * @param Logger $logger
     * @param PaymentDataHelper $paymentHelper
     * @param GetAllowedCountriesPerPaymentMethod $getAllowedCountriesPerPaymentMethod
     * @param SerializerInterface $serializer
     */
    public function __construct(
        Context $context,
        private readonly Config $payplugConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly PricingHelper $pricingHelper,
        private readonly CountryFactory $countryFactory,
        private readonly Resolver $localeResolver,
        private readonly Logger $logger,
        private readonly PaymentDataHelper $paymentHelper,
        private readonly GetAllowedCountriesPerPaymentMethod $getAllowedCountriesPerPaymentMethod,
        private readonly SerializerInterface $serializer
    ) {
        parent::__construct($context);
    }

    /**
     * Can display Oney payment method
     *
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function canDisplayOney(): bool
    {
        $storeId = $this->storeManager->getStore()->getId();

        try {
            $isSandbox = $this->payplugConfig->getIsSandbox($storeId);
            $apiKey = $this->payplugConfig->getApiKey($isSandbox, $storeId);
        } catch (Exception) {
            $this->logger->error('Could not retrieve Payplug API key for Oney');
            return false;
        }

        if (empty($apiKey)) {
            return false;
        }

        $oneyPaymentMethod = $this->getOneyMethod();
        if ($oneyPaymentMethod === '') {
            return false;
        }

        $isActive = $this->scopeConfig->getValue(
            'payment/' . $oneyPaymentMethod . '/active',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if (!$isActive) {
            return false;
        }

        $canUseOney = $this->scopeConfig->getValue(
            Config::CONFIG_PATH . 'can_use_oney',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if (!$canUseOney) {
            return false;
        }

        $currency = $this->storeManager->getStore()->getCurrentCurrencyCode();
        if ($this->getOneyAmounts($storeId, $currency) === false) {
            return false;
        }

        if (\Locale::getRegion($this->localeResolver->getLocale()) !== $this->getMerchandCountry()) {
            return false;
        }

        if ($this->getMerchantGuid() === '') {
            $this->logger->warning(
                'Oney merchant identifier is missing. Update your Payplug account information from the admin.'
            );

            return false;
        }

        return true;
    }

    /**
     * Get Oney widget loader url according to environment mode
     *
     * @return string
     */
    public function getWidgetLoaderUrl(): string
    {
        return $this->payplugConfig->getIsSandbox()
            ? self::WIDGET_LOADER_URL_TEST
            : self::WIDGET_LOADER_URL_LIVE;
    }

    /**
     * Is amount within Oney thresholds
     *
     * @param float $amount
     * @param int|null $storeId
     * @param string|null $currency
     * @return bool
     * @throws NoSuchEntityException
     */
    private function isAmountEligible(float $amount, ?int $storeId = null, ?string $currency = null): bool
    {
        $amountsByCurrency = $this->getOneyAmounts($storeId, $currency);
        if ($amountsByCurrency === false) {
            return false;
        }

        $amount = (int) round($amount * 100);

        return $amount >= $amountsByCurrency['min_amount'] && $amount <= $amountsByCurrency['max_amount'];
    }

    /**
     * Get Oney official widget configuration
     *
     * @param float|null $amount
     * @param string|null $paymentMethod
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getWidgetConfig(?float $amount = null, ?string $paymentMethod = null): array
    {
        $paymentMethod = $paymentMethod ?? $this->getOneyMethod();
        $amountsByCurrency = $this->getOneyAmounts();
        $codes = $this->getBusinessTransactionCodes($paymentMethod);
        $merchantGuid = $this->getMerchantGuid();
        $isEnabled = $merchantGuid !== '' && !empty($codes);

        return [
            'payment_method' => $paymentMethod,
            'loader_url' => $this->getWidgetLoaderUrl(),
            'country' => (string) $this->getMerchandCountry(),
            'language' => strtoupper(\Locale::getPrimaryLanguage($this->localeResolver->getLocale())),
            'merchant_guid' => $merchantGuid,
            'business_transaction_codes' => $codes,
            'options' => $codes ? array_keys($codes) : array_values(
                self::ALLOWED_OPERATIONS_BY_PAYMENT[$paymentMethod] ?? []
            ),
            'allowed_countries' => $this->getAllowedCountries(),
            'min_amount' => $amountsByCurrency ? $amountsByCurrency['min_amount'] / 100 : 0,
            'max_amount' => $amountsByCurrency ? $amountsByCurrency['max_amount'] / 100 : 0,
            'max_items' => self::MAX_ITEMS,
            'amount' => $amount,
            'is_enabled' => $isEnabled,
            'is_eligible' => $isEnabled && $amount !== null && $this->isAmountEligible($amount),
        ];
    }

    /**
     * Get Oney merchant identifier provided by Payplug account
     *
     * @return string
     */
    private function getMerchantGuid(): string
    {
        return trim((string) $this->payplugConfig->getConfigValue(SynchronizeAccountData::ONEY_MERCHANT_GUID_FIELD));
    }

    /**
     * Get Oney business transaction codes for a payment method, indexed by option type (3x, 4x)
     *
     * @param string $paymentMethod
     * @return array
     */
    private function getBusinessTransactionCodes(string $paymentMethod): array
    {
        $businessCodes = $this->serializer->unserialize(
            $this->payplugConfig->getConfigValue(SynchronizeAccountData::ONEY_BUSINESS_CODES_FIELD) ?: '[]'
        );

        $codes = [];
        foreach (self::ALLOWED_OPERATIONS_BY_PAYMENT[$paymentMethod] ?? [] as $operation => $type) {
            if (!empty($businessCodes[$operation])) {
                $codes[$type] = (string) $businessCodes[$operation];
            }
        }

        return $codes;
    }

    /**
     * Get Oney allowed countries
     *
     * @return array
     */
    private function getAllowedCountries(): array
    {
        return array_values($this->getAllowedCountriesPerPaymentMethod->execute(OneyConfig::METHOD_CODE));
    }

    /**
     * Is current store locale Italian (multi-store: an Italian store view of a non Italian merchant)
     *
     * @return bool
     */
    public function isItalianStore(): bool
    {
        return $this->localeResolver->getLocale() === 'it_IT';
    }

    /**
     * Is merchand Italian
     *
     * @return bool
     */
    public function isMerchandItalian(): bool
    {
        return $this->getMerchandCountry() === 'IT';
    }

    /**
     * Get More Info Url
     *
     * @return string
     */
    public function getMoreInfoUrl(): string
    {
        return 'https://www.payplug.com/hubfs/ONEY/payplug-italy.pdf';
    }

    /**
     * Get more info url
     *
     * @return string
     */
    public function getMoreInfoUrlWithoutFees(): string
    {
        return 'https://www.payplug.com/hubfs/ONEY/payplug-italy-no-fees.pdf';
    }

    /**
     * Get PayPlug merchand country, stored when the Payplug account information is synchronized
     *
     * @return string
     */
    private function getMerchandCountry(): string
    {
        return (string) $this->payplugConfig->getConfigValue('merchand_country');
    }

    /**
     * Validate Oney availability on amount
     *
     * @param float       $amount
     * @param int|null    $storeId
     * @param string|null $currency
     *
     * @throws Exception
     */
    private function validateAmount($amount, $storeId = null, $currency = null): bool
    {
        if (!$this->isAmountEligible((float) $amount, $storeId, $currency)) {
            throw new Exception($this->getAmountRangeMessage($storeId, $currency));
        }

        return true;
    }

    /**
     * Get the message explaining the Oney amount range
     *
     * @param int|null $storeId
     * @param string|null $currency
     * @return string
     * @throws NoSuchEntityException
     */
    public function getAmountRangeMessage(?int $storeId = null, ?string $currency = null): string
    {
        $amountsByCurrency = $this->getOneyAmounts($storeId, $currency);
        if ($amountsByCurrency === false) {
            return '';
        }

        return (string) __(
            'To pay with Oney, the total amount of your cart must be between %1 and %2.',
            $this->pricingHelper->currency($amountsByCurrency['min_amount'] / 100, true, false),
            $this->pricingHelper->currency($amountsByCurrency['max_amount'] / 100, true, false)
        );
    }

    /**
     * Get Oney available amounts
     *
     * @param null|mixed $storeId
     * @param null|string $currency
     *
     * @return array|bool
     * @throws NoSuchEntityException
     */
    public function getOneyAmounts($storeId = null, $currency = null)
    {
        if ($storeId === null) {
            $storeId = $this->storeManager->getStore()->getId();
        }
        if ($currency === null) {
            $currency = $this->storeManager->getStore()->getCurrentCurrencyCode();
        }

        return $this->payplugConfig->getAmountsByCurrency(
            $currency,
            (int)$storeId,
            Config::ONEY_CONFIG_PATH,
            'oney_'
        );
    }

    /**
     * Validate Oney on country
     *
     * @param string $countryCode
     * @param bool   $throwException
     *
     * @return bool
     *
     * @throws Exception
     */
    private function validateCountry($countryCode, $throwException = true): bool
    {
        $oneyCountries = $this->getAllowedCountries();

        if (!in_array($countryCode, $oneyCountries)) {
            if (!$throwException) {
                return false;
            }
            $countryNames = [];
            $locale = $this->localeResolver->getLocale();
            foreach ($oneyCountries as $oneyCountryCode) {
                $country = $this->countryFactory->create()->loadByCode($oneyCountryCode);
                $countryNames[] = $country->getName($locale);
            }
            throw new Exception(__(
                'Shipping and billing addresses must be both located in %1 to pay with Oney.',
                implode(', ', $countryNames)
            ));
        }

        return true;
    }

    /**
     * Count cart items
     *
     * @param array|QuoteItem[] $items
     */
    public function getCartItemsCount($items): int
    {
        $count = 0;
        foreach ($items as $item) {
            if ($item->isDeleted() || $item->getChildren()) {
                continue;
            }

            $itemQty = $item->getQty();
            if ($item->getParentItem()) {
                $itemQty = $itemQty * $item->getParentItem()->getQty();
            }
            $count += (int) $itemQty;
        }

        return $count;
    }

    /**
     * Validate Oney on checkout countries
     *
     * @param string $billingCountry
     * @param string $shippingCountry
     *
     * @throws Exception
     */
    private function validateCheckoutCountries($billingCountry, $shippingCountry): void
    {
        if (!empty($billingCountry) && !empty($shippingCountry) && $billingCountry !== $shippingCountry) {
            throw new Exception(__('Shipping and billing adresses must be both in the same country.'));
        }
        if (!$this->validateCountry($billingCountry, false)) {
            throw new Exception(__('Unavailable for the specified country'));
        }
    }

    /**
     * Validate items count
     *
     * @param int $countItems
     *
     * @throws Exception
     */
    private function validateItemsCount($countItems): void
    {
        if ($countItems >= self::MAX_ITEMS) {
            throw new Exception(__('To pay with Oney, your cart must contain less than %1 items.', self::MAX_ITEMS));
        }
    }

    /**
     * Validate Oney selected option
     *
     * @param string $paymentMethod
     * @param string $oneyOption
     *
     * @return string
     *
     * @throws Exception
     */
    public function validateOneyOption($paymentMethod, $oneyOption)
    {
        if (empty($oneyOption)) {
            throw new Exception(__('Please select a payment option for Oney.'));
        }

        $oneyOptionKey = array_search($oneyOption, self::ALLOWED_OPERATIONS_BY_PAYMENT[$paymentMethod] ?? []);
        if ($oneyOptionKey === false) {
            throw new Exception(__('Please select a valid payment option for Oney.'));
        }

        return $oneyOptionKey;
    }

    /**
     * Handle Oney checkout validation
     *
     * @param string $billingCountry
     * @param string $shippingCountry
     * @param int    $countItems
     *
     * @throws Exception
     */
    public function oneyCheckoutValidation($billingCountry, $shippingCountry, $countItems): void
    {
        $this->validateCheckoutCountries($billingCountry, $shippingCountry);
        $this->validateItemsCount($countItems);
    }

    /**
     * Handle Oney validation
     *
     * @param float       $amount
     * @param string      $countryCode
     * @param int|null    $storeId
     * @param string|null $currency
     *
     * @throws Exception
     */
    public function oneyValidation($amount, $countryCode, $storeId = null, $currency = null): void
    {
        $this->validateAmount($amount, $storeId, $currency);
        $this->validateCountry($countryCode);
    }

    /**
     * Get available Oney method
     *
     * @return string
     * @throws LocalizedException
     */
    private function getOneyMethod(): string
    {
        if ($this->oneyMethod === null) {
            $oneyMethods = [
                OneyConfig::METHOD_CODE,
                OneyWithoutFees::METHOD_CODE,
            ];
            $this->oneyMethod = '';
            foreach ($oneyMethods as $oneyMethod) {
                if ($this->paymentHelper->getMethodInstance($oneyMethod)->isAvailable()) {
                    $this->oneyMethod = $oneyMethod;
                    break;
                }
            }
        }

        return $this->oneyMethod;
    }
}
