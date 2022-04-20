<?php

namespace Payplug\Payments\Helper;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Locale\Resolver;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order\Item;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Payplug\Exception\PayplugException;
use Payplug\OneySimulation;
use Payplug\Payments\Gateway\Config\OneyWithoutFees;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\Api\Login;
use Payplug\Payments\Model\OneySimulation\Option;
use Payplug\Payments\Model\OneySimulation\Result;
use Payplug\Payments\Model\OneySimulation\Schedule;

class Oney extends AbstractHelper
{
    public const ALLOWED_OPERATIONS_BY_PAYMENT = [
        \Payplug\Payments\Gateway\Config\Oney::METHOD_CODE => [
            'x3_with_fees' => '3x',
            'x4_with_fees' => '4x',
        ],
        \Payplug\Payments\Gateway\Config\OneyWithoutFees::METHOD_CODE => [
            'x3_without_fees' => '3x',
            'x4_without_fees' => '4x',
        ],
    ];

    public const MAX_ITEMS = 1000;

    /**
     * @var Config
     */
    private $payplugConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var PricingHelper
     */
    private $pricingHelper;

    /**
     * @var CountryFactory
     */
    private $countryFactory;

    /**
     * @var Resolver
     */
    private $localeResolver;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var \Magento\Payment\Helper\Data
     */
    private $paymentHelper;

    /**
     * @var Login
     */
    private $login;

    /**
     * @var WriterInterface
     */
    private $writer;

    /**
     * @var AdapterInterface
     */
    private $resourceConnection;

    /**
     * @var string
     */
    private $oneyMethod;

    /**
     * @param Context                      $context
     * @param Config                       $payplugConfig
     * @param StoreManagerInterface        $storeManager
     * @param PricingHelper                $pricingHelper
     * @param CountryFactory               $countryFactory
     * @param Resolver                     $localeResolver
     * @param Logger                       $logger
     * @param CheckoutSession              $checkoutSession
     * @param CustomerSession              $customerSession
     * @param \Magento\Payment\Helper\Data $paymentHelper
     * @param Login                        $login
     * @param WriterInterface              $writer
     * @param ResourceConnection           $resourceConnection
     */
    public function __construct(
        Context $context,
        Config $payplugConfig,
        StoreManagerInterface $storeManager,
        PricingHelper $pricingHelper,
        CountryFactory $countryFactory,
        Resolver $localeResolver,
        Logger $logger,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        \Magento\Payment\Helper\Data $paymentHelper,
        Login $login,
        WriterInterface $writer,
        ResourceConnection $resourceConnection
    ) {
        parent::__construct($context);

        $this->payplugConfig = $payplugConfig;
        $this->storeManager = $storeManager;
        $this->pricingHelper = $pricingHelper;
        $this->countryFactory = $countryFactory;
        $this->localeResolver = $localeResolver;
        $this->logger = $logger;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->paymentHelper = $paymentHelper;
        $this->login = $login;
        $this->writer = $writer;
        $this->resourceConnection = $resourceConnection->getConnection();
    }

    /**
     * Check Oney availability
     *
     * @return bool
     */
    public function canDisplayOney(): bool
    {
        $storeId = $this->storeManager->getStore()->getId();
        $testApiKey = $this->scopeConfig->getValue(
            Config::CONFIG_PATH . 'test_api_key',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $liveApiKey = $this->scopeConfig->getValue(
            Config::CONFIG_PATH . 'live_api_key',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if (empty($testApiKey) && empty($liveApiKey)) {
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

        $storeLocale = $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $storeId);
        $localeCountry = explode('_', $storeLocale)[1] ?? null;
        if ($localeCountry !== $this->getMerchandCountry()) {
            return false;
        }

        return true;
    }

    /**
     * Check if merchand has an italian PayPlug account
     *
     * @return bool
     */
    public function isMerchandItalian()
    {
        return $this->getMerchandCountry() === 'IT';
    }

    /**
     * Get more info url
     *
     * @return string
     */
    public function getMoreInfoUrl()
    {
        return 'https://www.payplug.com/hubfs/ONEY/payplug-italy.pdf';
    }

    /**
     * Get more info url
     *
     * @return string
     */
    public function getMoreInfoUrlWithoutFees()
    {
        return 'https://www.payplug.com/hubfs/ONEY/payplug-italy-no-fees.pdf';
    }

    /**
     * Get PayPlug merchand country
     *
     * @return mixed|string
     */
    private function getMerchandCountry()
    {
        $storeId = $this->storeManager->getStore()->getId();
        $savedMerchandCountry = $this->getMerchandCountryFromConfig($storeId);
        if (!empty($savedMerchandCountry)) {
            return $savedMerchandCountry;
        }

        try {
            $testApiKey = $this->scopeConfig->getValue(
                Config::CONFIG_PATH . 'test_api_key',
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            $liveApiKey = $this->scopeConfig->getValue(
                Config::CONFIG_PATH . 'live_api_key',
                ScopeInterface::SCOPE_STORE,
                $storeId
            );

            $apiKey = $liveApiKey;
            $environmentMode = $this->scopeConfig->getValue(
                Config::CONFIG_PATH . 'environmentmode',
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            if ($environmentMode == Config::ENVIRONMENT_TEST) {
                $apiKey = $testApiKey;
            }

            $result = $this->login->getAccount($apiKey);
            if (!$result['status']) {
                return '';
            }
            $country = $result['answer']['country'] ?? '';
            $this->writer->save(
                Config::CONFIG_PATH . 'merchand_country',
                $country,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );

            return $country;
        } catch (\Exception $e) {
            $this->logger->error('Could not retrieve Payplug merchand country', [
                'exception' => $e,
            ]);

            return '';
        }
    }

    /**
     * Get merchand country from configuration
     *
     * @param int $storeId
     *
     * @return mixed|string
     */
    private function getMerchandCountryFromConfig(int $storeId)
    {
        $savedMerchandCountry = $this->scopeConfig->getValue(
            Config::CONFIG_PATH . 'merchand_country',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if (!empty($savedMerchandCountry)) {
            return $savedMerchandCountry;
        }

        $select = $this->resourceConnection->select()
            ->from(
                ['main_table' => $this->resourceConnection->getTableName('core_config_data')],
                'value'
            )
            ->where('main_table.scope like ?', ScopeInterface::SCOPE_STORE . '%')
            ->where('main_table.scope_id = ?', $storeId)
            ->where('main_table.path = ?', Config::CONFIG_PATH . 'merchand_country');

        return $this->resourceConnection->fetchOne($select);
    }

    /**
     * Validate Oney availability on amount
     *
     * @param float       $amount
     * @param int|null    $storeId
     * @param string|null $currency
     *
     * @return bool
     *
     * @throws \Exception
     */
    private function validateAmount($amount, $storeId = null, $currency = null): bool
    {
        $amountsByCurrency = $this->getOneyAmounts($storeId, $currency);
        $amount = (int) round($amount * 100);

        if ($amount < $amountsByCurrency['min_amount'] || $amount > $amountsByCurrency['max_amount']) {
            throw new \Exception(__(
                'To pay with Oney, the total amount of your cart must be between %1 and %2.',
                $this->pricingHelper->currency($amountsByCurrency['min_amount'] / 100, true, false),
                $this->pricingHelper->currency($amountsByCurrency['max_amount'] / 100, true, false)
            ));
        }

        return true;
    }

    /**
     * Get Oney available amounts
     *
     * @param null|mixed  $storeId
     * @param null|string $currency
     *
     * @return array|bool
     */
    public function getOneyAmounts($storeId = null, $currency = null)
    {
        if ($storeId === null) {
            $storeId = $this->storeManager->getStore()->getId();
        }
        if ($currency === null) {
            $currency = $this->storeManager->getStore()->getCurrentCurrencyCode();
        }

        return $this->payplugConfig->getAmountsByCurrency($currency, $storeId, 'oney_');
    }

    /**
     * Validate Oney on country
     *
     * @param string $countryCode
     * @param bool   $throwException
     *
     * @return bool
     *
     * @throws \Exception
     */
    private function validateCountry($countryCode, $throwException = true): bool
    {
        $storeId = $this->storeManager->getStore()->getId();
        $oneyCountries = $this->scopeConfig->getValue(
            Config::CONFIG_PATH . 'oney_countries',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $oneyCountries = json_decode($oneyCountries, true);

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
            throw new \Exception(__(
                'Shipping and billing addresses must be both located in %1 to pay with Oney.',
                implode(', ', $countryNames)
            ));
        }

        return true;
    }

    /**
     * Get country for Oney
     *
     * @return string
     */
    private function getDefaultCountry(): string
    {
        $quote = $this->checkoutSession->getQuote();
        $billingAddress = $quote->getBillingAddress();
        if (!empty($billingAddress->getCountryId())) {
            return $billingAddress->getCountryId();
        }

        if (!$quote->isVirtual()) {
            $shippingAddress = $quote->getShippingAddress();
            if (!empty($shippingAddress->getCountryId())) {
                return $shippingAddress->getCountryId();
            }
        }

        if ($this->customerSession->isLoggedIn()) {
            $defaultBilling = $this->customerSession->getCustomer()->getDefaultBillingAddress();
            if ($defaultBilling !== false && !empty($defaultBilling->getCountryId())) {
                return $defaultBilling->getCountryId();
            }
            if (!$quote->isVirtual()) {
                $defaultShipping = $this->customerSession->getCustomer()->getDefaultShippingAddress();
                if ($defaultShipping !== false && !empty($defaultShipping->getCountryId())) {
                    return $defaultShipping->getCountryId();
                }
            }
        }

        return 'FR';
    }

    /**
     * Get shipping method mapping for Oney
     *
     * @param string|null $shippingMethod
     *
     * @return array
     */
    public function getShippingMethodMapping($shippingMethod = null)
    {
        if ($shippingMethod === null) {
            return [
                'type' => 'edelivery',
                'period' => 3,
            ];
        }

        return [
            'type' => 'storepickup',
            'period' => 0,
        ];
    }

    /**
     * Get Oney simulation for checkout
     *
     * @param float  $amount
     * @param string $billingCountry
     * @param string $shippingCountry
     * @param string $paymentMethod
     *
     * @return Result
     */
    public function getOneySimulationCheckout($amount, $billingCountry, $shippingCountry, $paymentMethod = null): Result
    {
        $qty = $this->getCartItemsCount($this->checkoutSession->getQuote()->getAllItems());
        try {
            $this->oneyCheckoutValidation($billingCountry, $shippingCountry, $qty);
        } catch (\Exception $e) {
            $simulationResult = new Result();
            $simulationResult->setSuccess(false);
            $simulationResult->setMessage($e->getMessage());

            return $simulationResult;
        }

        return $this->getOneySimulation(
            $amount,
            $billingCountry ?? $shippingCountry ?? null,
            $qty,
            false,
            $paymentMethod
        );
    }

    /**
     * Count cart items
     *
     * @param array|Quote\Item[] $items
     *
     * @return int
     */
    public function getCartItemsCount($items)
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
     * @throws \Exception
     */
    private function validateCheckoutCountries($billingCountry, $shippingCountry)
    {
        if (!empty($billingCountry) && !empty($shippingCountry) && $billingCountry !== $shippingCountry) {
            throw new \Exception(__('Shipping and billing adresses must be both in the same country.'));
        }
        if (!$this->validateCountry($billingCountry, false)) {
            throw new \Exception(__('Unavailable for the specified country'));
        }
    }

    /**
     * Validate items count
     *
     * @param int $countItems
     *
     * @throws \Exception
     */
    private function validateItemsCount($countItems)
    {
        if ($countItems >= self::MAX_ITEMS) {
            throw new \Exception(__('To pay with Oney, your cart must contain less than %1 items.', self::MAX_ITEMS));
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
     * @throws \Exception
     */
    public function validateOneyOption($paymentMethod, $oneyOption)
    {
        if (empty($oneyOption)) {
            throw new \Exception(__('Please select a payment option for Oney.'));
        }

        $oneyOptionKey = array_search($oneyOption, self::ALLOWED_OPERATIONS_BY_PAYMENT[$paymentMethod] ?? []);
        if ($oneyOptionKey === false) {
            throw new \Exception(__('Please select a valid payment option for Oney.'));
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
     * @throws \Exception
     */
    public function oneyCheckoutValidation($billingCountry, $shippingCountry, $countItems)
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
     * @throws \Exception
     */
    public function oneyValidation($amount, $countryCode, $storeId = null, $currency = null)
    {
        $this->validateAmount($amount, $storeId, $currency);
        $this->validateCountry($countryCode);
    }

    /**
     * Get Oney simulation
     *
     * @param float|null  $amount
     * @param string|null $countryCode
     * @param int|null    $qty
     * @param bool        $validationOnly
     * @param string      $paymentMethod
     *
     * @return Result
     */
    public function getOneySimulation(
        $amount = null,
        $countryCode = null,
        $qty = null,
        $validationOnly = false,
        $paymentMethod = null
    ): Result {
        if ($amount === null) {
            $amount = $this->checkoutSession->getQuote()->getGrandTotal();
        }
        if ($countryCode === null) {
            $countryCode = $this->getDefaultCountry();
        }
        if ($qty === null) {
            $qty = $this->getCartItemsCount($this->checkoutSession->getQuote()->getAllItems());
        }
        $paymentMethod = $paymentMethod ?? $this->getOneyMethod();
        try {
            $this->oneyValidation($amount, $countryCode);
            $this->validateItemsCount($qty);

            if ($validationOnly) {
                $simulationResult = new Result();
                $simulationResult->setSuccess(true);
                $simulationResult->setMethod($paymentMethod);

                return $simulationResult;
            } else {
                return $this->getSimulation($amount, $countryCode, $paymentMethod);
            }
        } catch (PayplugException $e) {
            $this->logger->error($e->__toString());

            return $this->getMockSimulationResult($paymentMethod);
        } catch (\Exception $e) {
            $simulationResult = new Result();
            $simulationResult->setSuccess(false);
            $simulationResult->setMessage($e->getMessage());
            $simulationResult->setMethod($paymentMethod);

            return $simulationResult;
        }
    }

    /**
     * Get Oney mock simulation
     *
     * @param string $paymentMethod
     *
     * @return Result
     */
    private function getMockSimulationResult($paymentMethod)
    {
        $simulationResult = new Result();
        $simulationResult->setSuccess(true);
        $simulationResult->setMessage(__('Your payment schedule simulation is temporarily unavailable. ' .
            'You will find this information at the payment stage.'));
        $simulationResult->setMethod($paymentMethod);

        $operations = self::ALLOWED_OPERATIONS_BY_PAYMENT[$paymentMethod] ?? [];

        foreach ($operations as $type) {
            $option = new Option();
            $option->setType($type);
            $simulationResult->addOption($option);
        }

        return $simulationResult;
    }

    /**
     * Get Oney simulation
     *
     * @param float  $amount
     * @param string $countryCode
     * @param string $paymentMethod
     *
     * @return Result
     */
    private function getSimulation($amount, $countryCode, $paymentMethod): Result
    {
        $storeId = $this->storeManager->getStore()->getId();
        $isSandbox = $this->payplugConfig->getIsSandbox($storeId);
        $this->payplugConfig->setPayplugApiKey($storeId, $isSandbox);

        $operations = self::ALLOWED_OPERATIONS_BY_PAYMENT[$paymentMethod] ?? [];

        $data = [
            'amount' => (int) round($amount * 100),
            'country' => $countryCode,
            'operations' => array_keys($operations),
        ];
        $simulations = OneySimulation::getSimulations($data);

        $result = new Result();
        $result->setSuccess(true);
        $result->setAmount($amount);
        $result->setMethod($paymentMethod);

        foreach ($operations as $operation => $type) {
            if (!isset($simulations[$operation])) {
                $this->logger->warning(sprintf(
                    "Operation %s is not available. Amount was %f, country was %s",
                    $operation,
                    $amount,
                    $countryCode
                ));
                continue;
            }
            $simulation = $simulations[$operation];
            $option = new Option();

            $totalCost = $simulation['down_payment_amount'];
            foreach ($simulation['installments'] as $installment) {
                $schedule = new Schedule();
                $schedule->setAmount($installment['amount'] / 100);
                $schedule->setDate(new \DateTime($installment['date']));
                $totalCost += $installment['amount'];
                $option->addSchedule($schedule);
            }

            $option->setType($type);
            $option->setCost($simulation['total_cost'] / 100);
            $option->setRate($simulation['effective_annual_percentage_rate']);
            $option->setFirstDeposit($simulation['down_payment_amount'] / 100);
            $option->setTotalAmount($totalCost / 100);
            $result->addOption($option);
        }

        return $result;
    }

    /**
     * Get available Oney method
     *
     * @return string
     */
    private function getOneyMethod()
    {
        if ($this->oneyMethod === null) {
            $oneyMethods = [
                \Payplug\Payments\Gateway\Config\Oney::METHOD_CODE,
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
