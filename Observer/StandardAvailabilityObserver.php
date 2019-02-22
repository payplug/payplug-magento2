<?php

namespace Payplug\Payments\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Model\Method\Adapter;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config;

class StandardAvailabilityObserver implements ObserverInterface
{
    /**
     * @var Config
     */
    private $payplugConfig;

    /**
     * CurrencyValidator constructor.
     *
     * @param Config                 $payplugConfig
     */
    public function __construct(Config $payplugConfig)
    {
        $this->payplugConfig = $payplugConfig;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var CartInterface $quote */
        $quote = $observer->getData('quote');
        if ($quote === null) {
            return;
        }
        /** @var DataObject $checkResult */
        $checkResult = $observer->getData('result');
        /** @var Adapter $adapter */
        $adapter = $observer->getData('method_instance');

        if ($adapter->getCode() != 'payplug_payments_standard') {
            return;
        }

        $storeId = $quote->getStoreId();

        if (!$adapter->getConfigData('active', $storeId)) {
            $checkResult->setData('is_available', false);
            return;
        }

        $testApiKey = $this->payplugConfig->getConfigValue(
            'test_api_key',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $liveApiKey = $this->payplugConfig->getConfigValue(
            'live_api_key',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if (empty($testApiKey) && empty($liveApiKey)) {
            $checkResult->setData('is_available', false);
            return;
        }

        $currency = $quote->getCurrency()->getQuoteCurrencyCode();
        if (!$amountsByCurrency = $this->getAmountsByCurrency($currency, $quote->getStoreId())) {
            $checkResult->setData('is_available', false);
            return;
        }
        $amount = $quote->getGrandTotal() * 100;

        if ($amount < $amountsByCurrency['min_amount'] || $amount > $amountsByCurrency['max_amount']) {
            $checkResult->setData('is_available', false);
            return;
        }
    }

    /**
     * Get valid range of amount for a given currency
     *
     * @param string $isoCode
     * @param int    $storeId
     *
     * @return bool|array
     */
    private function getAmountsByCurrency($isoCode, $storeId)
    {
        $minAmounts = [];
        $maxAmounts = [];
        $minAmountsConfig = $this->payplugConfig->getConfigValue('min_amounts', ScopeInterface::SCOPE_STORE, $storeId);
        $maxAmountsConfig = $this->payplugConfig->getConfigValue('max_amounts', ScopeInterface::SCOPE_STORE, $storeId);
        foreach (explode(';', $minAmountsConfig) as $amountCur) {
            $cur = [];
            if (preg_match('/^([A-Z]{3}):([0-9]*)$/', $amountCur, $cur)) {
                $minAmounts[$cur[1]] = (int)$cur[2];
            } else {
                return false;
            }
        }
        foreach (explode(';', $maxAmountsConfig) as $amountCur) {
            $cur = [];
            if (preg_match('/^([A-Z]{3}):([0-9]*)$/', $amountCur, $cur)) {
                $maxAmounts[$cur[1]] = (int)$cur[2];
            } else {
                return false;
            }
        }

        if (!isset($minAmounts[$isoCode]) || !isset($maxAmounts[$isoCode])) {
            return false;
        } else {
            $currentMinAmount = $minAmounts[$isoCode];
            $currentMaxAmount = $maxAmounts[$isoCode];
        }

        return ['min_amount' => $currentMinAmount, 'max_amount' => $currentMaxAmount];
    }
}
