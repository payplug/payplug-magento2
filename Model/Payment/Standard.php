<?php

namespace Payplug\Payments\Model\Payment;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\UrlInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\ScopeInterface;
use Payplug\Core\HttpClient;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Helper\Card as CardHelper;
use Payplug\Payments\Helper\Data as PayplugHelper;
use Payplug\Payments\Model\Customer\Card;
use Payplug\Payments\Model\Customer\CardFactory as PayplugCardFactory;
use Payplug\Payments\Model\CustomerCardRepository;
use Payplug\Payments\Model\Order\PaymentFactory as PayplugPaymentFactory;
use Payplug\Payments\Model\Order\ProcessingFactory as PayplugOrderProcessingFactory;
use Payplug\Payments\Model\OrderPaymentRepository;
use Payplug\Payments\Model\OrderProcessingRepository;

class Standard extends AbstractPaymentMethod
{
    const METHOD_CODE = 'payplug_payments_standard';

    /**
     * @var string
     */
    protected $_formBlockType = \Magento\Payment\Block\Form::class;

    /**
     * @var string
     */
    protected $_infoBlockType = \Payplug\Payments\Block\Info::class;

    /**
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var PayplugPaymentFactory
     */
    protected $payplugPaymentFactory;

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
     * @param Config                                                       $payplugConfig
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
        Config $payplugConfig,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        DirectoryHelper $directory = null
    ) {
        parent::__construct(
            $context,
            $registry,
            $scopeConfig,
            $objectManager,
            $urlBuilder,
            $payplugLogger,
            $payplugHelper,
            $invoiceService,
            $orderRepository,
            $orderPaymentRepository,
            $orderSender,
            $orderProcessingFactory,
            $orderProcessingRepository,
            $payplugConfig,
            $resource,
            $resourceCollection,
            $data,
            $directory
        );

        $this->payplugPaymentFactory = $paymentFactory;
        $this->cardFactory = $cardFactory;
        $this->customerCardRepository = $customerCardRepository;
        $this->cardHelper = $cardHelper;
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
            $metadata = ['reason' => "Refunded with Magento."];

            $payplugPayment->makeRefund($amount, $metadata, $payment->getOrder()->getStoreId());
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
            $this->payplugConfig->getModuleVersion(),
            'Magento ' . $this->payplugConfig->getMagentoVersion()
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

        $isSandbox = $this->payplugConfig->getIsSandbox($order->getStoreId());

        $paymentTab = array_merge(
            $paymentTab,
            $this->buildPaymentData($order, $isSandbox, $customerCardId)
        );

        $this->payplugConfig->setPayplugApiKey($order->getStoreId(), $isSandbox);
        $payment = \Payplug\Payment::create($paymentTab);

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
     * @param bool     $isSandbox
     * @param int|null $customerCardId
     *
     * @return array
     */
    protected function buildPaymentData($order, $isSandbox, $customerCardId)
    {
        $paymentData = [];
        $paymentData['notification_url'] = $this->urlBuilder->getUrl('payplug_payments/payment/ipn', [
            'ipn_store_id' => $order->getStoreId(),
            'ipn_sandbox' => (int) $isSandbox,
        ]);
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
            $companyId = (int) $this->payplugConfig->getConfigValue('company_id',ScopeInterface::SCOPE_STORE, $storeId);
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
     * Check if PayPlug One-click payment is enabled
     *
     * @return bool
     */
    public function isOneClick()
    {
        return $this->getConfigData('one_click');
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
            if ($this->getConfigData('generate_invoice', $order->getStoreId())) {
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
            $payment = $payplugPayment->retrieve($order->getStoreId());
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
