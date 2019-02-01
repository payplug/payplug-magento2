<?php

namespace Payplug\Payments\Model;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\UrlInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\Data\PaymentMethodInterface;
use Magento\Framework\DataObject;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\ScopeInterface;
use Payplug\Core\HttpClient;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Helper\Card as CardHelper;
use Payplug\Payments\Helper\Data as PayplugHelper;
use Payplug\Payments\Model\Customer\Card;
use Payplug\Payments\Model\Customer\CardFactory as PayplugCardFactory;
use Payplug\Payments\Model\Order\PaymentFactory as PayplugPaymentFactory;
use Payplug\Payments\Model\Order\Processing;
use Payplug\Payments\Model\Order\ProcessingFactory as PayplugOrderProcessingFactory;
use Payplug\Payplug;

class PaymentMethod extends AbstractModel implements MethodInterface, PaymentMethodInterface
{
    const ENVIRONMENT_TEST = 'test';
    const ENVIRONMENT_LIVE = 'live';
    const PAYMENT_PAGE_REDIRECT = 'redirect';
    const PAYMENT_PAGE_EMBEDDED = 'embedded';
    const METHOD_CODE = 'payplug_payments';

    /**
     * @var string
     */
    protected $_formBlockType = \Magento\Payment\Block\Form::class;

    /**
     * @var string
     */
    protected $_infoBlockType = \Payplug\Payments\Block\Info::class;

    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var DirectoryHelper
     */
    private $directory;

    /**
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var Logger
     */
    protected $payplugLogger;

    /**
     * @var PayplugPaymentFactory
     */
    protected $payplugPaymentFactory;

    /**
     * @var PayplugHelper
     */
    protected $payplugHelper;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var OrderPaymentRepository
     */
    protected $orderPaymentRepository;

    /**
     * @var Order\Email\Sender\OrderSender
     */
    protected $orderSender;

    /**
     * @var PayplugCardFactory
     */
    protected $cardFactory;

    /**
     * @var CustomerCardRepository
     */
    protected $customerCardRepository;

    /**
     * @var CardHelper
     */
    protected $cardHelper;

    /**
     * @var PayplugOrderProcessingFactory
     */
    protected $orderProcessingFactory;

    /**
     * @var OrderProcessingRepository
     */
    protected $orderProcessingRepository;

    /**
     * @param \Magento\Framework\Model\Context                             $context
     * @param \Magento\Framework\Registry                                  $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface           $scopeConfig
     * @param \Magento\Framework\ObjectManagerInterface                    $objectManager
     * @param UrlInterface                                                 $urlBuilder
     * @param Logger                                                       $payplugLogger
     * @param PayplugPaymentFactory                                        $paymentFactory
     * @param PayplugHelper                                                $payplugHelper
     * @param InvoiceService                                               $invoiceService
     * @param OrderRepository                                              $orderRepository
     * @param OrderPaymentRepository                                       $orderPaymentRepository
     * @param Order\Email\Sender\OrderSender                               $orderSender
     * @param PayplugCardFactory                                           $cardFactory
     * @param CardHelper                                                   $cardHelper
     * @param CustomerCardRepository                                       $customerCardRepository
     * @param PayplugOrderProcessingFactory                                $orderProcessingFactory
     * @param OrderProcessingRepository                                    $orderProcessingRepository
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null           $resourceCollection
     * @param array                                                        $data
     * @param DirectoryHelper|null                                         $directory
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        UrlInterface $urlBuilder,
        Logger $payplugLogger,
        PayplugPaymentFactory $paymentFactory,
        PayplugHelper $payplugHelper,
        InvoiceService $invoiceService,
        OrderRepository $orderRepository,
        OrderPaymentRepository $orderPaymentRepository,
        Order\Email\Sender\OrderSender $orderSender,
        PayplugCardFactory $cardFactory,
        CustomerCardRepository $customerCardRepository,
        CardHelper $cardHelper,
        PayplugOrderProcessingFactory $orderProcessingFactory,
        OrderProcessingRepository $orderProcessingRepository,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        DirectoryHelper $directory = null
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);

        $this->scopeConfig = $scopeConfig;
        $this->objectManager = $objectManager;
        $this->urlBuilder = $urlBuilder;
        $this->payplugLogger = $payplugLogger;
        $this->payplugPaymentFactory = $paymentFactory;
        $this->payplugHelper = $payplugHelper;
        $this->invoiceService = $invoiceService;
        $this->orderRepository = $orderRepository;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->orderSender = $orderSender;
        $this->directory = $directory ?: $objectManager->get(DirectoryHelper::class);
        $this->cardFactory = $cardFactory;
        $this->customerCardRepository = $customerCardRepository;
        $this->cardHelper = $cardHelper;
        $this->orderProcessingFactory = $orderProcessingFactory;
        $this->orderProcessingRepository = $orderProcessingRepository;

        $validKey = self::setAPIKey();
        if ($validKey != null) {
            Payplug::setSecretKey($validKey);
        }
    }

    /**
     * @param int $storeId
     */
    public function setStore($storeId)
    {
        $this->setData('store', (int)$storeId);
    }

    /**
     * @return mixed
     */
    public function getStore()
    {
        return $this->getData('store');
    }

    /**
     * Check order availability
     *
     * @return bool
     */
    public function canOrder()
    {
        return false;
    }

    /**
     * Check authorize availability
     *
     * @return bool
     */
    public function canAuthorize()
    {
        return false;
    }

    /**
     * Check capture availability
     *
     * @return bool
     */
    public function canCapture()
    {
        return true;
    }

    /**
     * Check partial capture availability
     *
     * @return bool
     */
    public function canCapturePartial()
    {
        return false;
    }

    /**
     * Check whether capture can be performed once and no further capture possible
     *
     * @return bool
     */
    public function canCaptureOnce()
    {
        return false;
    }

    /**
     * Check refund availability
     *
     * @return bool
     */
    public function canRefund()
    {
        return true;
    }

    /**
     * Check partial refund availability for invoice
     *
     * @return bool
     */
    public function canRefundPartialPerInvoice()
    {
        return true;
    }

    /**
     * Check void availability
     *
     * @return bool
     */
    public function canVoid()
    {
        return false;
    }

    /**
     * Using internal pages for input payment data
     * Can be used in admin
     *
     * @return bool
     */
    public function canUseInternal()
    {
        return false;
    }

    /**
     * Can be used in regular checkout
     *
     * @return bool
     */
    public function canUseCheckout()
    {
        return true;
    }

    /**
     * Can be edit order (renew order)
     *
     * @return bool
     */
    public function canEdit()
    {
        return true;
    }

    /**
     * Check fetch transaction info availability
     *
     * @return bool
     */
    public function canFetchTransactionInfo()
    {
        return false;
    }

    /**
     * Fetch transaction info
     *
     * @param InfoInterface $payment
     * @param string $transactionId
     *
     * @return array
     */
    public function fetchTransactionInfo(InfoInterface $payment, $transactionId)
    {
        return [];
    }

    /**
     * Retrieve payment system relation flag
     *
     * @return bool
     */
    public function isGateway()
    {
        return false;
    }

    /**
     * Retrieve payment method online/offline flag
     *
     * @return bool
     */
    public function isOffline()
    {
        return false;
    }

    /**
     * Flag if we need to run payment initialize while order place
     *
     * @return bool
     */
    public function isInitializeNeeded()
    {
        return true;
    }

    /**
     * To check billing country is allowed for the payment method
     *
     * @param string $country
     *
     * @return bool
     */
    public function canUseForCountry($country)
    {
        /*
        for specific country, the flag will set up as 1
        */
        if ($this->getConfigData('allowspecific') == 1) {
            $availableCountries = explode(',', $this->getConfigData('specificcountry'));
            if (!in_array($country, $availableCountries)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check method for processing with base currency
     *
     * @param string $currencyCode
     *
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        return true;
    }

    /**
     * Retrieve payment method code
     *
     * @return string
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCode()
    {
        if (empty($this->_code)) {
            throw new \Magento\Framework\Exception\LocalizedException(__('We cannot retrieve the payment method code.'));
        }
        return $this->_code;
    }

    /**
     * Retrieve block type for method form generation
     *
     * @return string
     */
    public function getFormBlockType()
    {
        return $this->_formBlockType;
    }

    /**
     * Retrieve block type for display method information
     *
     * @return string
     */
    public function getInfoBlockType()
    {
        return $this->_infoBlockType;
    }

    /**
     * Retrieve payment information model object
     *
     * @return InfoInterface
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getInfoInstance()
    {
        $instance = $this->getData('info_instance');
        if (!$instance instanceof InfoInterface) {
            throw new \Magento\Framework\Exception\LocalizedException(__('We cannot retrieve the payment information object instance.'));
        }
        return $instance;
    }

    /**
     * Retrieve payment information model object
     *
     * @param InfoInterface $info
     *
     * @return void
     */
    public function setInfoInstance(InfoInterface $info)
    {
        $this->setData('info_instance', $info);
    }

    /**
     * Validate payment method information object
     *
     * @return $this
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function validate()
    {
        /**
         * to validate payment method is allowed for billing country or not
         */
        $paymentInfo = $this->getInfoInstance();
        if ($paymentInfo instanceof Payment) {
            $billingCountry = $paymentInfo->getOrder()->getBillingAddress()->getCountryId();
        } else {
            $billingCountry = $paymentInfo->getQuote()->getBillingAddress()->getCountryId();
        }
        $billingCountry = $billingCountry ?: $this->directory->getDefaultCountry();
        if (!$this->canUseForCountry($billingCountry)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('You can\'t use the payment type you selected to make payments to the billing country.')
            );
        }
        return $this;
    }

    /**
     * Order payment abstract method
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canOrder()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The order action is not available.'));
        }
        return $this;
    }

    /**
     * Authorize payment abstract method
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canAuthorize()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The authorize action is not available.'));
        }
        return $this;
    }

    /**
     * Capture payment abstract method
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canCapture()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The capture action is not available.'));
        }

        return $this;
    }

    /**
     * Refund specified amount for payment
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Exception
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canRefund()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The refund action is not available.'));
        }

        try {
            $payplugPayment = $this->payplugHelper->getOrderPayment($payment->getOrder()->getId());
            $paymentId = $payplugPayment->getPaymentId();
            $metadata = ['reason' => "Refunded with Magento."];

            $payplugPayment->makeRefund($paymentId, $amount, $metadata, $payment->getOrder()->getStoreId());
        } catch (NoSuchEntityException $e) {
            throw new \Exception(__('Could not find valid payplug order payment. Please try refunding offline.'));
        } catch (PayplugException $e) {
            $this->payplugLogger->error($e->__toString());
            throw new \Exception(__('Error while refunding online. Please try again or contact us.'));
        } catch (\Exception $e) {
            $this->payplugLogger->error($e->getMessage());
            throw new \Exception(__('Error while refunding online. Please try again or contact us.'));
        }

        return $this;
    }

    /**
     * Cancel payment abstract method
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     *
     * @return $this
     */
    public function cancel(\Magento\Payment\Model\InfoInterface $payment)
    {
        return $this;
    }

    /**
     * Void payment abstract method
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     *
     * @return $this
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function void(\Magento\Payment\Model\InfoInterface $payment)
    {
        if (!$this->canVoid()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The void action is not available.'));
        }
        return $this;
    }

    /**
     * Whether this method can accept or deny payment
     *
     * @return bool
     */
    public function canReviewPayment()
    {
        return false;
    }

    /**
     * Attempt to accept a payment that us under review
     *
     * @param InfoInterface $payment
     *
     * @return false
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function acceptPayment(InfoInterface $payment)
    {
        if (!$this->canReviewPayment()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The payment review action is unavailable.'));
        }
        return false;
    }

    /**
     * Attempt to deny a payment that us under review
     *
     * @param InfoInterface $payment
     *
     * @return false
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function denyPayment(InfoInterface $payment)
    {
        if (!$this->canReviewPayment()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The payment review action is unavailable.'));
        }
        return false;
    }

    /**
     * Retrieve payment method title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getConfigData('title');
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
     * @param int|string|null|\Magento\Store\Model\Store $storeId
     *
     * @return mixed
     */
    public function getConfigData($field, $storeId = null)
    {
        if ('order_place_redirect_url' === $field) {
            return $this->getOrderPlaceRedirectUrl();
        }
        if (null === $storeId) {
            $storeId = $this->getStore();
        }
        $path = 'payment/' . $this->getCode() . '/' . $field;
        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Assign data to info model instance
     *
     * @param array|\Magento\Framework\DataObject $data
     *
     * @return $this
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        $this->_eventManager->dispatch(
            'payment_method_assign_data_' . $this->getCode(),
            [
                AbstractDataAssignObserver::METHOD_CODE => $this,
                AbstractDataAssignObserver::MODEL_CODE => $this->getInfoInstance(),
                AbstractDataAssignObserver::DATA_CODE => $data
            ]
        );

        $this->_eventManager->dispatch(
            'payment_method_assign_data',
            [
                AbstractDataAssignObserver::METHOD_CODE => $this,
                AbstractDataAssignObserver::MODEL_CODE => $this->getInfoInstance(),
                AbstractDataAssignObserver::DATA_CODE => $data
            ]
        );

        return $this;
    }

    /**
     * Check whether payment method can be used
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     *
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if (!$this->isActive($quote ? $quote->getStoreId() : null)) {
            return false;
        }

        if ($quote !== null) {
            $testApiKey = $this->getConfigData('test_api_key', $quote->getStoreId());
            $liveApiKey = $this->getConfigData('live_api_key', $quote->getStoreId());
            if (empty($testApiKey) && empty($liveApiKey)) {
                return false;
            }

            $currency = $quote->getCurrency()->getQuoteCurrencyCode();
            if (!$amountsByCurrency = $this->getAmountsByCurrency($currency, $quote->getStoreId())) {
                return false;
            }
            $amount = $quote->getGrandTotal() * 100;

            if ($amount < $amountsByCurrency['min_amount'] || $amount > $amountsByCurrency['max_amount']) {
                return false;
            }
        }

        $checkResult = new DataObject();
        $checkResult->setData('is_available', true);

        // for future use in observers
        $this->_eventManager->dispatch(
            'payment_method_is_active',
            [
                'result' => $checkResult,
                'method_instance' => $this,
                'quote' => $quote
            ]
        );

        return $checkResult->getData('is_available');
    }

    /**
     * Is active
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isActive($storeId = null)
    {
        return (bool)(int)$this->getConfigData('active', $storeId);
    }

    /**
     * Method that will be executed instead of authorize or capture
     * if flag isInitializeNeeded set to true
     *
     * @param string $paymentAction
     * @param object $stateObject
     *
     * @return $this
     */
    public function initialize($paymentAction, $stateObject)
    {
        $payment = $this->getInfoInstance();
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);

        return $this;
    }

    /**
     * Get config payment action url
     * Used to universalize payment actions when processing payment place
     *
     * @return string
     */
    public function getConfigPaymentAction()
    {
        return $this->getConfigData('payment_action');
    }

    /**
     * Return url to redirect after confirming the order
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        $url = $this->urlBuilder->getUrl(
            'payplug_payments/payment/redirect',
            ['_secure' => true, '_nosid' => true]
        );

        return $url;
    }

    /**
     * Set up the right API Key in current context
     *
     * @param int|null    $storeId
     * @param string|null $environmentMode
     *
     * @return string
     */
    public function setAPIKey($storeId = null, $environmentMode = null)
    {
        if ($environmentMode === null) {
            $environmentMode = $this->getConfigData('environmentmode', $storeId);
        }
        $validKey = null;
        if ($environmentMode == self::ENVIRONMENT_TEST) {
            $validKey = $this->getConfigData('test_api_key', $storeId);
        } elseif ($environmentMode == self::ENVIRONMENT_LIVE) {
            $validKey = $this->getConfigData('live_api_key', $storeId);
        }

        return $validKey;
    }

    /**
     * Generate payplug transaction
     *
     * @param Order    $order
     * @param int|null $customerCardId
     *
     * @return \Payplug\Resource\Payment
     */
    public function createPayplugTransaction($order, $customerCardId = null)
    {
        HttpClient::addDefaultUserAgentProduct(
            'PayPlug-Magento2',
            $this->payplugHelper->getModuleVersion(),
            'Magento ' . $this->payplugHelper->getMagentoVersion()
        );

        $currency = $order->getOrderCurrencyCode();
        $quoteId = $order->getQuoteId();

        if ($currency === null) {
            $currency = 'EUR';
        }

        //metadata
        $metadata = [
            'ID Quote' => $quoteId,
            'Order'    => $order->getIncrementId(),
            'Website'  => $_SERVER['HTTP_HOST']
        ];

        //payment
        $paymentTab = [
            'amount' => $order->getGrandTotal() * 100,
            'currency' => $currency,
            'customer' => $this->buildCustomerData($order),
            'metadata' => $metadata
        ];

        $paymentTab = array_merge(
            $paymentTab,
            $this->buildPaymentData($order, $customerCardId)
        );

        $payment = \Payplug\Payment::create($paymentTab);

        $isSandbox = $this->payplugHelper->getIsSandbox($order->getStoreId());
        /** @var \Payplug\Payments\Model\Order\Payment $orderPayment */
        $orderPayment = $this->payplugPaymentFactory->create();
        $orderPayment->setOrderId($order->getId());
        $orderPayment->setPaymentId($payment->id);
        $orderPayment->setIsSandbox($isSandbox);
        $this->orderPaymentRepository->save($orderPayment);

        return $payment;
    }

    /**
     * Build customer data for payment transaction
     *
     * @param Order $order
     *
     * @return array
     */
    protected function buildCustomerData($order)
    {
        $address = null;
        if ($order->getShippingAddress() !== false) {
            $address = $order->getShippingAddress();
        } elseif ($order->getBillingAddress() !== false) {
            $address = $order->getBillingAddress();
        }

        $addressStreet    = 'no data';
        $addressStreet2   = null;
        $addressPostcode  = '00000';
        $addressCity      = 'no data';
        $addressCountryId = 'FR';
        if ($address !== null) {
            $street1 = $address->getStreetLine(1);
            if (!empty($street1)) {
                $addressStreet = $street1;
            }
            $street2 = $address->getStreetLine(2);
            if (!empty($street2)) {
                $addressStreet2 = $street2;
            }
            $addressPostcode = $address->getPostcode();
            $addressCity = $address->getCity();
            $addressCountryId = $address->getCountryId();
        }
        $firstname = $order->getCustomerFirstname();
        $lastname = $order->getCustomerLastname();
        if ($order->getBillingAddress() !== false) {
            $firstname = $order->getBillingAddress()->getFirstname();
            $lastname = $order->getBillingAddress()->getLastname();
        }

        return [
            'first_name' => $firstname,
            'last_name' => $lastname,
            'email' => $order->getCustomerEmail(),
            'address1' => $addressStreet,
            'address2' => $addressStreet2,
            'postcode' => $addressPostcode,
            'city' => $addressCity,
            'country' => $addressCountryId,
        ];
    }

    /**
     * Build payment data for payment transaction
     *
     * @param Order    $order
     * @param int|null $customerCardId
     *
     * @return array
     */
    protected function buildPaymentData($order, $customerCardId)
    {
        $paymentData = [];
        $paymentData['notification_url'] = $this->urlBuilder->getUrl('payplug_payments/payment/ipn', ['ipn_store_id' => $order->getStoreId()]);
        $paymentData['force_3ds'] = false;

        $currentCard = $this->getCustomerCardToken($customerCardId, $order->getCustomerId());
        $paymentData['allow_save_card'] = $this->canSaveCard($currentCard, $order->getCustomerId());

        if ($this->isOneClick() && $currentCard != null) {
            $paymentData['payment_method'] = $currentCard;
        } else {
            $paymentData['hosted_payment'] = [
                'return_url' => $this->urlBuilder->getUrl('payplug_payments/payment/paymentReturn', [
                    '_secure'  => true,
                    'quote_id' => $order->getQuoteId(),
                ]),
                'cancel_url' => $this->urlBuilder->getUrl('payplug_payments/payment/cancel', [
                    '_secure'  => true,
                    'quote_id' => $order->getQuoteId(),
                ]),
            ];
        }

        return $paymentData;
    }

    /**
     * Check if card can be saved on payment page
     *
     * @param string|null $currentCard
     * @param int|null    $customerId
     *
     * @return bool
     */
    protected function canSaveCard($currentCard, $customerId)
    {
        if (!$this->isOneClick()) {
            return false;
        }

        if ($currentCard !== null) {
            return false;
        }

        if (empty($customerId)) {
            return false;
        }

        return true;
    }

    /**
     * Save customer card
     *
     * @param \Payplug\Resource\Payment $payment
     * @param int|null                  $customerId
     * @param int|null                  $storeId
     */
    public function saveCustomerCard($payment, $customerId, $storeId = null)
    {
        if ($customerId == null) {
            return;
        }

        if ($payment->save_card == 1 || ($payment->card->id != '' && $payment->hosted_payment != '')) {

            try {
                $this->customerCardRepository->get($payment->card->id, 'card_token');
                return;
            } catch (NoSuchEntityException $e) {
                // Nothing to do, we want to create card if it does not already exist
            }

            /** @var Card $card */
            $card = $this->cardFactory->create();
            $customerCardId = $this->cardHelper->getLastCardIdByCustomer($customerId) + 1;
            $companyId = (int) $this->getConfigData('company_id', $storeId);
            $cardDate = $payment->card->exp_year . '-' . $payment->card->exp_month;
            $expDate = date('Y-m-t 23:59:59', strtotime($cardDate));
            $brand = $payment->card->brand;
            if (!in_array(strtolower($payment->card->brand), ['visa', 'mastercard', 'maestro', 'carte_bancaire'])) {
                $brand = 'other';
            }

            $card->setCustomerId($customerId);
            $card->setCustomerCardId($customerCardId);
            $card->setCompanyId($companyId);
            $card->setIsSandbox(!$payment->is_live);
            $card->setCardToken($payment->card->id);
            $card->setLastFour($payment->card->last4);
            $card->setExpDate($expDate);
            $card->setBrand($brand);
            $card->setCountry($payment->card->country);
            $card->setMetadata($payment->card->metadata);

            $this->customerCardRepository->save($card);
        }
    }

    /**
     * Get customer card token
     *
     * @param int|null $customerCardId
     * @param int|null $customerId
     *
     * @return string|null
     *
     * @throws PaymentException
     */
    protected function getCustomerCardToken($customerCardId, $customerId)
    {
        $this->payplugLogger->error("[$customerCardId][$customerId]");
        if ($customerCardId === null) {
            return null;
        }

        if (empty($customerId)) {
            return null;
        }

        try {
            $currentCard = $this->cardHelper->getCustomerCard($customerId, $customerCardId);
        } catch (NoSuchEntityException $e) {
            throw new PaymentException(__('This card does not exists or has been deleted.'));
        }

        return $currentCard->getCardToken();
    }

    /**
     * Check if PayPlug One-click payment is enable
     *
     * @return bool
     */
    public function isOneClick()
    {
        return $this->payplugHelper->isOneClick();
    }

    /**
     * Change order status to Pending Payment
     *
     * @param Order $order
     */
    public function setOrderPendingPayment($order)
    {
        if ($order->getState() != Order::STATE_NEW) {
            return;
        }

        $status = $this->getConfigData('pendingpayment_order_status', $order->getStoreId());
        $order->addStatusToHistory($status, '');
        $this->orderRepository->save($order);
    }

    /**
     * Process order after payment validation
     *
     * @param Order  $order
     * @param string $paymentId
     *
     * @throws \Exception
     */
    public function processOrder($order, $paymentId)
    {
        try {
            $orderProcessing = $this->orderProcessingRepository->get($order->getId(), 'order_id');
            $createdAt = new \DateTime($orderProcessing->getCreatedAt());

            if ($createdAt > new \DateTime("now - 1 min")) {
                // Order is currently being processed
                return;
            }
            // Order has been set as processing for more than a minute
            // Delete and recreate a new flag
            $this->orderProcessingRepository->delete($orderProcessing);
        } catch (NoSuchEntityException $e) {
            // Order is not currently being processed
            // Create a new flag to block concurrent process
        }

        try {
            $orderProcessing = $this->createOrderProcessing($order);
        } catch (\Exception $e) {
            return;
        }

        try {
            $order = $this->orderRepository->get($order->getId());
            if ($order->getState() != Order::STATE_PENDING_PAYMENT &&
                $order->getState() != Order::STATE_NEW
            ) {
                $this->orderProcessingRepository->delete($orderProcessing);
                return;
            }

            $status = $this->getConfigData('processing_order_status', $order->getStoreId());
            $comment = sprintf(__('Payment has been captured by Payment Gateway. Transaction id: %s'), $paymentId);
            $transactionSave = $this->objectManager->create(\Magento\Framework\DB\Transaction::class);
            if ($this->payplugHelper->getConfigValue('generate_invoice', ScopeInterface::SCOPE_STORE, $order->getStoreId())) {
                if (!$order->canInvoice()) {
                    throw new \Exception(__('Cannot create an invoice.'));
                }

                $invoice = $this->invoiceService->prepareInvoice($order, []);
                if (!$invoice) {
                    throw new \Exception(__('Cannot create an invoice.'));
                }
                if (!$invoice->getTotalQty()) {
                    throw new \Exception(__('Cannot create an invoice without products.'));
                }

                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                $invoice->register();
                $invoice->setTransactionId($paymentId);
                $invoice->getOrder()->setCustomerNoteNotify(false);

                $transactionSave->addObject($invoice);
            } else {
                $comment .= ' - ' . __("Invoice can be manually created.");
            }

            $order->setIsInProcess(true);
            $order->addStatusToHistory($status, $comment);

            $transactionSave->addObject($order);
            $transactionSave->save();

            $this->sentNewOrderEmail($order);
        } finally {
            $this->orderProcessingRepository->delete($orderProcessing);
        }
    }

    /**
     * @param Order $order
     *
     * @return Processing
     */
    protected function createOrderProcessing($order)
    {
        /** @var Processing $orderProcessing */
        $orderProcessing = $this->orderProcessingFactory->create();
        $orderProcessing->setOrderId($order->getId());
        $date = new \DateTime();
        $orderProcessing->setCreatedAt($date->format('Y-m-d H:i:s'));
        $this->orderProcessingRepository->save($orderProcessing);

        return $orderProcessing;
    }

    /**
     * @param Order $order
     */
    protected function sentNewOrderEmail($order)
    {
        try {
            $this->orderSender->send($order);
        } catch (\Exception $e) {
            $this->payplugLogger->critical($e);
        }
    }

    /**
     * Cancel order
     *
     * @param Order       $order
     * @param bool        $isCanceledByPayplug
     * @param string|null $failureMessage
     *
     * @return void
     *
     * @throws \Exception
     */
    public function cancelOrder($order, $isCanceledByPayplug = true, $failureMessage = null)
    {
        if ($order->getState() != Order::STATE_CANCELED) {
            if (!$order->canCancel()) {
                throw new \Exception('Order cannot be canceled anymore.');
            } else {
                $comment = '';
                if ($isCanceledByPayplug) {
                    $comment = __('Canceled by Payment Provider');
                }
                if ($failureMessage !== null) {
                    $comment = $failureMessage;
                }

                $status = $this->getConfigData('canceled_order_status', $order->getStoreId());
                $order->cancel();

                $order->addStatusToHistory($status, $comment);
                $this->orderRepository->save($order);
            }
        }
    }

    /**
     * Check if order's payment can be updated
     *
     * @param Order $order
     *
     * @return bool
     */
    public function canUpdatePayment($order)
    {
        if ($order->getPayment() === false) {
            return false;
        }

        if ($order->getPayment()->getMethodInstance()->getCode() != $this->getCode()) {
            return false;
        }

        $finalStates = [Order::STATE_CANCELED, Order::STATE_CLOSED];
        if (in_array($order->getState(), $finalStates)) {
            return false;
        }

        return true;
    }

    /**
     * Update order's payment
     *
     * @param Order $order
     *
     * @return void
     */
    public function updatePayment($order)
    {
        $payplugPayment = $this->payplugHelper->getOrderPayment($order->getId());

        if ($payplugPayment->getId()) {
            $environmentMode = self::ENVIRONMENT_LIVE;
            if ($payplugPayment->isSandbox()) {
                $environmentMode = self::ENVIRONMENT_TEST;
            }

            $paymentId = $payplugPayment->getPaymentId();
            $payment = $payplugPayment->retrieve($paymentId, $environmentMode, $order->getStoreId());
            if ($payment->failure) {
                $failureMessage = $this->payplugHelper->getPaymentErrorMessage($payment);
                $this->cancelOrder($order, false, $failureMessage);
            } elseif ($payment->is_paid) {
                $this->processOrder($order, $payment->id);
            }
            $this->saveCustomerCard($payment, $order->getCustomerId(), $order->getStoreId());
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
    public function getAmountsByCurrency($isoCode, $storeId)
    {
        $minAmounts = [];
        $maxAmounts = [];
        foreach (explode(';', $this->getConfigData('min_amounts', $storeId)) as $amountCur) {
            $cur = [];
            if (preg_match('/^([A-Z]{3}):([0-9]*)$/', $amountCur, $cur)) {
                $minAmounts[$cur[1]] = (int)$cur[2];
            } else {
                return false;
            }
        }
        foreach (explode(';', $this->getConfigData('max_amounts', $storeId)) as $amountCur) {
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

    /**
     * Full refund order
     *
     * @param Order $order
     * @param float $amountToRefund
     */
    public function fullRefundOrder($order, $amountToRefund)
    {
        if (!$order->canCreditmemo()) {
            return;
        }
        if ($order->getCreditmemosCollection()->getSize() > 0) {
            return;
        }
        if ($order->getTotalRefunded() > 0) {
            return;
        }
        $amountToRefund = $amountToRefund / 100;
        if ((float)$amountToRefund != (float)$order->getTotalPaid()) {
            return;
        }

        $order->getPayment()->registerRefundNotification($amountToRefund);
    }
}
