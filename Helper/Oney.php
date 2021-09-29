<?php

namespace Payplug\Payments\Helper;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
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
use Payplug\Payments\Model\OneySimulation\Option;
use Payplug\Payments\Model\OneySimulation\Result;
use Payplug\Payments\Model\OneySimulation\Schedule;

class Oney extends AbstractHelper
{
    const ALLOWED_OPERATIONS_BY_PAYMENT = [
        \Payplug\Payments\Gateway\Config\Oney::METHOD_CODE => [
            'x3_with_fees' => '3x',
            'x4_with_fees' => '4x',
        ],
        \Payplug\Payments\Gateway\Config\OneyWithoutFees::METHOD_CODE => [
            'x3_without_fees' => '3x',
            'x4_without_fees' => '4x',
        ],
    ];

    const MAX_ITEMS = 1000;

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
     * @var string
     */
    private $oneyMethod;

    /**
     * @param Context               $context
     * @param Config                $payplugConfig
     * @param StoreManagerInterface $storeManager
     * @param PricingHelper         $pricingHelper
     * @param CountryFactory        $countryFactory
     * @param Resolver              $localeResolver
     * @param Logger                $logger
     * @param CheckoutSession       $checkoutSession
     * @param CustomerSession       $customerSession
     * @param \Magento\Payment\Helper\Data $paymentHelper
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
        \Magento\Payment\Helper\Data $paymentHelper
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
    }

    /**
     * @return bool
     */
    public function canDisplayOney(): bool
    {
        $storeId = $this->storeManager->getStore()->getId();
        $testApiKey = $this->scopeConfig->getValue(Config::CONFIG_PATH . 'test_api_key', ScopeInterface::SCOPE_STORE, $storeId);
        $liveApiKey = $this->scopeConfig->getValue(Config::CONFIG_PATH . 'live_api_key', ScopeInterface::SCOPE_STORE, $storeId);

        if (empty($testApiKey) && empty($liveApiKey)) {
            return false;
        }

        $oneyPaymentMethod = $this->getOneyMethod();
        if ($oneyPaymentMethod === '') {
            return false;
        }

        $isActive = $this->scopeConfig->getValue('payment/' . $oneyPaymentMethod . '/active', ScopeInterface::SCOPE_STORE, $storeId);
        if (!$isActive) {
            return false;
        }

        $canUseOney = $this->scopeConfig->getValue(Config::CONFIG_PATH . 'can_use_oney', ScopeInterface::SCOPE_STORE, $storeId);
        if (!$canUseOney) {
            return false;
        }

        $currency = $this->storeManager->getStore()->getCurrentCurrencyCode();
        if ($this->getOneyAmounts($storeId, $currency) === false) {
            return false;
        }

        return true;
    }

    /**
     * @param float $amount
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
     * @param string $countryCode
     *
     * @return bool
     *
     * @throws \Exception
     */
    private function validateCountry($countryCode): bool
    {
        $storeId = $this->storeManager->getStore()->getId();
        $oneyCountries = $this->scopeConfig->getValue(Config::CONFIG_PATH . 'oney_countries', ScopeInterface::SCOPE_STORE, $storeId);
        $oneyCountries = json_decode($oneyCountries, true);

        if (!in_array($countryCode, $oneyCountries)) {
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

        return $this->getOneySimulation($amount, $billingCountry ?? $shippingCountry ?? null, $qty, false, $paymentMethod);
    }

    /**
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
    }

    /**
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
     * @param float|null  $amount
     * @param string|null $countryCode
     * @param int|null    $qty
     * @param bool        $validationOnly
     * @param string      $paymentMethod
     *
     * @return Result
     */
    public function getOneySimulation($amount = null, $countryCode = null, $qty = null, $validationOnly = false, $paymentMethod = null): Result
    {
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
     * @param $paymentMethod
     *
     * @return Result
     */
    private function getMockSimulationResult($paymentMethod)
    {
        $simulationResult = new Result();
        $simulationResult->setSuccess(true);
        $simulationResult->setMessage(__('Your payment schedule simulation is temporarily unavailable. You will find this information at the payment stage.'));
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
