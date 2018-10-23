<?php

namespace Payplug\Payments\Model;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\UrlInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\TransparentInterface;
use Magento\Quote\Api\Data\PaymentMethodInterface;
use Magento\Framework\DataObject;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\ScopeInterface;
use Payplug\Core\HttpClient;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Helper\Data as PayplugHelper;
use Payplug\Payments\Model\Order\PaymentFactory as PayplugPaymentFactory;
use Payplug\Payplug;

class PaymentMethod extends AbstractExtensibleModel implements TransparentInterface, PaymentMethodInterface
{
    const ENVIRONMENT_TEST = 'test';
    const ENVIRONMENT_LIVE = 'live';
    const PAYMENT_PAGE_REDIRECT = 'redirect';
    const PAYMENT_PAGE_EMBEDDED = 'embedded';

    const ACTION_ORDER = 'order';

    const ACTION_AUTHORIZE = 'authorize';

    const ACTION_AUTHORIZE_CAPTURE = 'authorize_capture';

    const STATUS_UNKNOWN = 'UNKNOWN';

    const STATUS_APPROVED = 'APPROVED';

    const STATUS_ERROR = 'ERROR';

    const STATUS_DECLINED = 'DECLINED';

    const STATUS_VOID = 'VOID';

    const STATUS_SUCCESS = 'SUCCESS';

    /**
     * Different payment method checks.
     */
    const CHECK_USE_FOR_COUNTRY = 'country';

    const CHECK_USE_FOR_CURRENCY = 'currency';

    const CHECK_USE_CHECKOUT = 'checkout';

    const CHECK_USE_INTERNAL = 'internal';

    const CHECK_ORDER_TOTAL_MIN_MAX = 'total';

    const CHECK_ZERO_TOTAL = 'zero_total';

    const GROUP_OFFLINE = 'offline';

    /**
     * @var string
     */
    protected $_formBlockType = \Magento\Payment\Block\Form::class;

    /**
     * @var string
     */
    protected $_infoBlockType = \Magento\Payment\Block\Info::class;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isGateway = false;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isOffline = false;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canOrder = false;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canAuthorize = false;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canCapturePartial = false;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canCaptureOnce = false;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canVoid = false;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canUseInternal = false;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canUseCheckout = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isInitializeNeeded = false;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canFetchTransactionInfo = false;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canReviewPayment = false;

    /**
     * TODO: whether a captured transaction may be voided by this gateway
     * This may happen when amount is captured, but not settled
     * @var bool
     */
    protected $_canCancelInvoice = false;

    /**
     * Fields that should be replaced in debug with '***'
     *
     * @var array
     */
    protected $_debugReplacePrivateDataKeys = [];

    /**
     * Payment data
     *
     * @var \Magento\Payment\Helper\Data
     */
    protected $_paymentData;

    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Magento\Payment\Model\Method\Logger
     */
    protected $logger;

    /**
     * @var DirectoryHelper
     */
    private $directory;

    /**
     * @var string
     */
    protected $_code = 'payplug_payments';

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
     * @param \Magento\Framework\Model\Context                             $context
     * @param \Magento\Framework\Registry                                  $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory            $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory                 $customAttributeFactory
     * @param \Magento\Payment\Helper\Data                                 $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface           $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger                         $logger
     * @param UrlInterface                                                 $urlBuilder
     * @param Logger                                                       $payplugLogger
     * @param PayplugPaymentFactory                                        $paymentFactory
     * @param PayplugHelper                                                $payplugHelper
     * @param InvoiceService                                               $invoiceService
     * @param \Magento\Framework\ObjectManagerInterface                    $objectManager
     * @param OrderRepository                                              $orderRepository
     * @param OrderPaymentRepository                                       $orderPaymentRepository
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null           $resourceCollection
     * @param array                                                        $data
     * @param DirectoryHelper|null                                         $directory
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        UrlInterface $urlBuilder,
        Logger $payplugLogger,
        PayplugPaymentFactory $paymentFactory,
        PayplugHelper $payplugHelper,
        InvoiceService $invoiceService,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        OrderRepository $orderRepository,
        OrderPaymentRepository $orderPaymentRepository,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        DirectoryHelper $directory = null
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $resource,
            $resourceCollection,
            $data
        );
        $this->_paymentData = $paymentData;
        $this->_scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->directory = $directory ?: ObjectManager::getInstance()->get(DirectoryHelper::class);
        $this->initializeData($data);
        $this->urlBuilder = $urlBuilder;
        $this->payplugLogger = $payplugLogger;
        $this->payplugPaymentFactory = $paymentFactory;
        $this->payplugHelper = $payplugHelper;
        $this->invoiceService = $invoiceService;
        $this->objectManager = $objectManager;
        $this->orderRepository = $orderRepository;
        $this->orderPaymentRepository = $orderPaymentRepository;

        $validKey = self::setAPIKey();
        if ($validKey != null) {
            Payplug::setSecretKey($validKey);
        }
    }

    /**
     * Initializes injected data
     *
     * @param array $data
     *
     * @return void
     */
    protected function initializeData($data = [])
    {
        if (!empty($data['formBlockType'])) {
            $this->_formBlockType = $data['formBlockType'];
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
        return $this->_canOrder;
    }

    /**
     * Check authorize availability
     *
     * @return bool
     */
    public function canAuthorize()
    {
        return $this->_canAuthorize;
    }

    /**
     * Check capture availability
     *
     * @return bool
     */
    public function canCapture()
    {
        return $this->_canCapture;
    }

    /**
     * Check partial capture availability
     *
     * @return bool
     */
    public function canCapturePartial()
    {
        return $this->_canCapturePartial;
    }

    /**
     * Check whether capture can be performed once and no further capture possible
     *
     * @return bool
     */
    public function canCaptureOnce()
    {
        return $this->_canCaptureOnce;
    }

    /**
     * Check refund availability
     *
     * @return bool
     */
    public function canRefund()
    {
        return $this->_canRefund;
    }

    /**
     * Check partial refund availability for invoice
     *
     * @return bool
     */
    public function canRefundPartialPerInvoice()
    {
        return $this->_canRefundInvoicePartial;
    }

    /**
     * Check void availability
     *
     * @return bool
     */
    public function canVoid()
    {
        return $this->_canVoid;
    }

    /**
     * Using internal pages for input payment data
     * Can be used in admin
     *
     * @return bool
     */
    public function canUseInternal()
    {
        return $this->_canUseInternal;
    }

    /**
     * Can be used in regular checkout
     *
     * @return bool
     */
    public function canUseCheckout()
    {
        return $this->_canUseCheckout;
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
        return $this->_canFetchTransactionInfo;
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
        return $this->_isGateway;
    }

    /**
     * Retrieve payment method online/offline flag
     *
     * @return bool
     */
    public function isOffline()
    {
        return $this->_isOffline;
    }

    /**
     * Flag if we need to run payment initialize while order place
     *
     * @return bool
     */
    public function isInitializeNeeded()
    {
        return $this->_isInitializeNeeded;
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
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canRefund()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The refund action is not available.'));
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
        return $this->_canReviewPayment;
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
        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
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
     * @return $this
     */
    public function getConfigInterface()
    {
        return $this;
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
            $environmentMode = $this->getConfigData('environment_mode', $storeId);
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
     * @param Order $order
     *
     * @return \Payplug\Resource\Payment
     */
    public function createPayplugTransaction($order)
    {
        HttpClient::addDefaultUserAgentProduct(
            'PayPlug-Magento',
            '1.4.0.0',
            'Magento 1.9.3.6'
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
            $this->buildPaymentData($order)
        );

        $payment = \Payplug\Payment::create($paymentTab);

        $isSandbox = $this->payplugHelper->getIsSandbox($order->getStoreId());
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

        return [
            'first_name' => $order->getCustomerFirstname(),
            'last_name' => $order->getCustomerLastname(),
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
     * @param Order $order
     *
     * @return array
     */
    protected function buildPaymentData($order)
    {
        $paymentData = [];
        $paymentData['notification_url'] = $this->urlBuilder->getUrl('payplug_payments/payment/ipn', ['ipn_store_id' => $order->getStoreId()]);
        $paymentData['force_3ds'] = false;

        $paymentData['allow_save_card'] = false;

        $paymentData['hosted_payment'] = [
            'return_url' => $this->urlBuilder->getUrl('payplug_payments/payment/paymentReturn', [
                '_secure' => true,
                'quote_id' => $order->getQuoteId(),
            ]),
            'cancel_url' => $this->urlBuilder->getUrl('payplug_payments/payment/cancel', [
                '_secure' => true,
                'quote_id' => $order->getQuoteId(),
            ]),
        ];

        return $paymentData;
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
        $order->addCommentToStatusHistory('', $status);
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
        if ($order->getState() != Order::STATE_PENDING_PAYMENT &&
            $order->getState() != Order::STATE_NEW
        ) {
            return;
        }

        $comment = sprintf(__('Payment has been captured by Payment Gateway. Transaction id: %s'), $paymentId);
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
            $invoice->getOrder()->setCustomerNoteNotify(false);
            $invoice->getOrder()->setIsInProcess(true);

            $status = $this->getConfigData('processing_order_status', $order->getStoreId());
            $order->addCommentToStatusHistory($comment, $status);
//            $order->sendNewOrderEmail(); // TODO send order email

            $transactionSave = $this->objectManager->create(
                \Magento\Framework\DB\Transaction::class
            )->addObject(
                $invoice
            )->addObject(
                $invoice->getOrder()
            );
            $transactionSave->save();
        } else {
            // If auto generate invoice is not activated, keep current status
            $comment .= ' - ' . __("Invoice can be manually created.");
            $order->addCommentToStatusHistory($comment, false);
//            $order->sendNewOrderEmail(); // TODO send order email
        }
    }
}
