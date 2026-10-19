<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Model\Payment;

use Magento\Directory\Model\Currency;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Payment\Model\Checks\TotalMinMax;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config;

class AmountBounds
{
    public const MIN = 'min';
    public const MAX = 'max';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param CurrencyFactory $currencyFactory
     * @param array $methods Prefix of the Payplug limits by payment method code
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CurrencyFactory $currencyFactory,
        private readonly array $methods = []
    ) {
    }

    /**
     * Get the order total limits set by Payplug for a payment method, in cents of the given currency
     *
     * @param string $methodCode
     * @param string $currency
     * @param string $scopeType
     * @param int $scopeId
     * @return array|null Null when Payplug provides no limits for this currency
     */
    public function getAccountBounds(string $methodCode, string $currency, string $scopeType, int $scopeId): ?array
    {
        $path = Config::CONFIG_PATH . ($this->methods[$methodCode] ?? '');
        $minAmounts = $this->parseAmounts($this->scopeConfig->getValue($path . 'min_amounts', $scopeType, $scopeId));
        $maxAmounts = $this->parseAmounts($this->scopeConfig->getValue($path . 'max_amounts', $scopeType, $scopeId));

        if (!isset($minAmounts[$currency], $maxAmounts[$currency])) {
            return null;
        }

        return [self::MIN => $minAmounts[$currency], self::MAX => $maxAmounts[$currency]];
    }

    /**
     * Get the order total limits set by the merchant (Minimum / Maximum Order Total), in cents of the base currency
     *
     * @param string $methodCode
     * @param string $scopeType
     * @param int $scopeId
     * @return array Min and max limits, null when not set
     */
    private function getMerchantBounds(string $methodCode, string $scopeType, int $scopeId): array
    {
        return [
            self::MIN => $this->readMerchantAmount($methodCode, TotalMinMax::MIN_ORDER_TOTAL, $scopeType, $scopeId),
            self::MAX => $this->readMerchantAmount($methodCode, TotalMinMax::MAX_ORDER_TOTAL, $scopeType, $scopeId),
        ];
    }

    /**
     * Get the merchant limits within the Payplug limits of a payment method, in cents of the given currency
     *
     * @param string $methodCode
     * @param string $currency
     * @param string $scopeType
     * @param int $scopeId
     * @return array|null Null when Payplug provides no limits or no amount fits both limits
     */
    public function getEffectiveBounds(string $methodCode, string $currency, string $scopeType, int $scopeId): ?array
    {
        $accountBounds = $this->getAccountBounds($methodCode, $currency, $scopeType, $scopeId);

        if ($accountBounds === null) {
            return null;
        }

        $merchantBounds = $this->convertFromBaseCurrency(
            $this->getMerchantBounds($methodCode, $scopeType, $scopeId),
            $currency,
            $scopeType,
            $scopeId
        );
        $min = max($accountBounds[self::MIN], $merchantBounds[self::MIN] ?? $accountBounds[self::MIN]);
        $max = min($accountBounds[self::MAX], $merchantBounds[self::MAX] ?? $accountBounds[self::MAX]);

        return $min <= $max ? [self::MIN => $min, self::MAX => $max] : null;
    }

    /**
     * Is the quote grand total within the Payplug limits of a payment method, in the quote currency
     *
     * @param string $methodCode
     * @param CartInterface $quote
     * @return bool
     */
    public function isQuoteWithinAccountBounds(string $methodCode, CartInterface $quote): bool
    {
        $bounds = $this->getAccountBounds(
            $methodCode,
            (string) $quote->getCurrency()?->getQuoteCurrencyCode(),
            ScopeInterface::SCOPE_STORE,
            (int) $quote->getStoreId()
        );

        $grandTotal = $this->toCents((float) $quote->getGrandTotal());

        return $bounds !== null && $grandTotal >= $bounds[self::MIN] && $grandTotal <= $bounds[self::MAX];
    }

    /**
     * Get the base currency of a configuration scope
     *
     * @param string $scopeType
     * @param int $scopeId
     * @return string
     */
    public function getBaseCurrency(string $scopeType, int $scopeId): string
    {
        return (string) $this->scopeConfig->getValue(Currency::XML_PATH_CURRENCY_BASE, $scopeType, $scopeId);
    }

    /**
     * Format an amount in cents
     *
     * @param int $amount
     * @param string $currency
     * @return string
     */
    public function formatAmount(int $amount, string $currency): string
    {
        return $this->currencyFactory->create()
            ->load($currency)
            ->formatPrecision($amount / 100, PriceCurrencyInterface::DEFAULT_PRECISION, [], false);
    }

    /**
     * Convert merchant limits from the base currency of the scope to the given currency, unchanged without rate
     *
     * @param array $bounds
     * @param string $currency
     * @param string $scopeType
     * @param int $scopeId
     * @return array
     */
    private function convertFromBaseCurrency(array $bounds, string $currency, string $scopeType, int $scopeId): array
    {
        $baseCurrency = $this->getBaseCurrency($scopeType, $scopeId);

        if ($baseCurrency === $currency) {
            return $bounds;
        }

        $rate = (float) $this->currencyFactory->create()->load($baseCurrency)->getRate($currency);

        if ($rate <= 0.0) {
            return $bounds;
        }

        return array_map(
            static fn (?int $amount): ?int => $amount === null ? null : (int) round($amount * $rate),
            $bounds
        );
    }

    /**
     * Parse amounts by currency saved by the account synchronization, e.g. EUR:99;GBP:100
     *
     * @param mixed $amounts
     * @return array
     */
    private function parseAmounts(mixed $amounts): array
    {
        $parsedAmounts = [];

        foreach (explode(';', (string) $amounts) as $amount) {
            if (preg_match('/^([A-Z]{3}):(\d+)$/', trim($amount), $matches)) {
                $parsedAmounts[$matches[1]] = (int) $matches[2];
            }
        }

        return $parsedAmounts;
    }

    /**
     * Read a merchant limit; an empty value means no limit, as in Magento\Payment\Model\Checks\TotalMinMax
     *
     * @param string $methodCode
     * @param string $field
     * @param string $scopeType
     * @param int $scopeId
     * @return int|null
     */
    private function readMerchantAmount(string $methodCode, string $field, string $scopeType, int $scopeId): ?int
    {
        $value = $this->scopeConfig->getValue(sprintf('payment/%s/%s', $methodCode, $field), $scopeType, $scopeId);

        if (empty($value) || !is_numeric($value)) {
            return null;
        }

        return $this->toCents((float) $value);
    }

    /**
     * Convert an amount to cents
     *
     * @param float $amount
     * @return int
     */
    private function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
