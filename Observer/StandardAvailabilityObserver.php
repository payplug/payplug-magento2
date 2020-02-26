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
use Payplug\Payments\Gateway\Config\InstallmentPlan;
use Payplug\Payments\Gateway\Config\Oney;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Helper\Data;

class StandardAvailabilityObserver implements ObserverInterface
{
    /**
     * @var Config
     */
    private $payplugConfig;

    /**
     * @var Data
     */
    private $payplugHelper;

    /**
     * @param Config $payplugConfig
     */
    public function __construct(Config $payplugConfig, Data $payplugHelper)
    {
        $this->payplugConfig = $payplugConfig;
        $this->payplugHelper = $payplugHelper;
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

        if (!$this->payplugHelper->isCodePayplugPayment($adapter->getCode())) {
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

        $prefix = '';
        if ($adapter->getCode() == Oney::METHOD_CODE) {
            $prefix = 'oney_';
        }

        $currency = $quote->getCurrency()->getQuoteCurrencyCode();
        $amountsByCurrency = $this->payplugConfig->getAmountsByCurrency($currency, $quote->getStoreId(), $prefix);
        if ($amountsByCurrency === false) {
            $checkResult->setData('is_available', false);
            return;
        }

        // Oney can be displayed (disabled) in checkout
        // Do not check amount to confirm validity
        if ($adapter->getCode() == Oney::METHOD_CODE) {
            return;
        }

        $amount = (int) round($quote->getGrandTotal() * 100);

        if ($amount < $amountsByCurrency['min_amount'] || $amount > $amountsByCurrency['max_amount']) {
            $checkResult->setData('is_available', false);
            return;
        }
        
        if ($adapter->getCode() == InstallmentPlan::METHOD_CODE) {
            if ($quote->getGrandTotal() < $adapter->getConfigData('threshold')) {
                $checkResult->setData('is_available', false);
                return;
            }
        }
    }
}
