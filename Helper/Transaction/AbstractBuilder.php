<?php

namespace Payplug\Payments\Helper\Transaction;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Payment\Gateway\Data\AddressAdapterInterface;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order\Address;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Helper\Country;
use Payplug\Payments\Helper\Phone;
use Payplug\Payments\Logger\Logger;

abstract class AbstractBuilder extends AbstractHelper
{
    /**
     * @var Config
     */
    protected $payplugConfig;

    /**
     * @var Country
     */
    protected $countryHelper;

    /**
     * @var Phone
     */
    protected $phoneHelper;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param Context      $context
     * @param Config       $payplugConfig
     * @param Country      $countryHelper
     * @param Phone        $phoneHelper
     * @param Logger       $logger
     */
    public function __construct(
        Context $context,
        Config $payplugConfig,
        Country $countryHelper,
        Phone $phoneHelper,
        Logger $logger
    ) {
        parent::__construct($context);

        $this->payplugConfig = $payplugConfig;
        $this->countryHelper = $countryHelper;
        $this->phoneHelper = $phoneHelper;
        $this->logger = $logger;
    }

    /**
     * Build PayPlug payment transaction
     *
     * @param OrderAdapterInterface $order
     * @param InfoInterface         $payment
     * @param Quote                 $quote
     *
     * @return array
     */
    public function buildTransaction($order, $payment, $quote)
    {
        $transaction = array_merge(
            $this->buildAmountData($order),
            $this->buildCustomerData($order, $payment, $quote),
            $this->buildOrderData($order, $quote),
            $this->buildPaymentData($order, $payment, $quote)
        );

        $this->logger->info('New transaction', [
            'details' => $transaction,
        ]);

        return $transaction;
    }

    /**
     * Build amount data
     *
     * @param OrderAdapterInterface $order
     *
     * @return array
     */
    public function buildAmountData($order)
    {
        $paymentTab = [
            'amount' => (int) round(($order->getGrandTotalAmount() ?: $order->getBaseGrandTotal()) * 100),
        ];
        return $paymentTab;
    }

    /**
     * Build customer data
     *
     * @param OrderAdapterInterface $order
     * @param InfoInterface         $payment
     * @param Quote                 $quote
     *
     * @return array
     */
    public function buildCustomerData($order, $payment, $quote)
    {
        $language = $this->payplugConfig->getConfigValue(
            'code',
            ScopeInterface::SCOPE_STORE,
            $order->getStoreId(),
            'general/locale/'
        );
        $language = substr($language, 0, 2);

        $allowedCountries = $this->countryHelper->getAllowedCountries();
        $defaultCountry = $payment->getMethodInstance()->getConfigData('default_country', $order->getStoreId());
        if (empty($defaultCountry)) {
            $defaultCountry = 'FR';
        }

        $email = $order->getBillingAddress()->getEmail();

        $billingData = $this->buildAddressData(
            $order->getBillingAddress(),
            $language,
            $allowedCountries,
            $defaultCountry
        );
        $billingData['email'] = $email;
        $shippingData = $billingData;
        $deliveryType = 'OTHER';
        if ($order->getShippingAddress() !== null) {
            $deliveryType = 'NEW';
            $shippingCustomerAddressId = $quote->getShippingAddress()->getCustomerAddressId();
            if (!empty($shippingCustomerAddressId)) {
                if ($shippingCustomerAddressId == $quote->getBillingAddress()->getCustomerAddressId()) {
                    $deliveryType = 'BILLING';
                }
            } elseif ($quote->getShippingAddress()->getSameAsBilling()) {
                $deliveryType = 'BILLING';
            }
            $shippingData = $this->buildAddressData(
                $order->getShippingAddress(),
                $language,
                $allowedCountries,
                $defaultCountry
            );
            $shippingData['email'] = $email;
        }
        $shippingData['delivery_type'] = $deliveryType;

        return [
            'billing' => $billingData,
            'shipping' => $shippingData,
        ];
    }

    /**
     * Build customer address data
     *
     * @param object $address
     * @param string $language
     * @param array  $allowedCountries
     * @param string $defaultCountry
     *
     * @return array
     */
    private function buildAddressData($address, $language, $allowedCountries, $defaultCountry)
    {
        $street1 = null;
        $street2 = null;
        if ($address instanceof AddressAdapterInterface) {
            $street1 = $address->getStreetLine1();
            $street2 = $address->getStreetLine2() ?: null;
        } elseif ($address instanceof Address) {
            $street1 = $address->getStreetLine(1);
            $street2 = $address->getStreetLine(2) ?: null;
        } else {
            $this->logger->error('Unhandled address type when building payplug transaction', [
                'class' => get_class($address),
            ]);
        }

        $country = $address->getCountryId();
        if (!in_array($country, $allowedCountries)) {
            $country = $defaultCountry;
        }

        $phoneResult = $this->phoneHelper->getPhoneInfo($address->getTelephone(), $address->getCountryId());
        $mobile = null;
        $landline = null;
        if (is_array($phoneResult)) {
            if ($phoneResult['landline']) {
                $landline = $phoneResult['phone'];
            }
            if ($phoneResult['mobile']) {
                $mobile = $phoneResult['phone'];
            }
        }

        $prefix = strtolower($address->getPrefix() ?? '');
        $allowedPrefixes = ['mr', 'mrs', 'miss'];
        if (!in_array($prefix, $allowedPrefixes)) {
            $prefix = null;
        }

        return [
            'title' => $prefix,
            'first_name' => $address->getFirstname(),
            'last_name' => $address->getLastname(),
            'mobile_phone_number' => $mobile,
            'landline_phone_number' => $landline,
            'address1' => $street1,
            'address2' => $street2,
            'postcode' => $address->getPostcode(),
            'city' => $address->getCity(),
            'state' => $address->getRegionCode() ?: null,
            'country' => $country,
            'language' => $language,
        ];
    }

    /**
     * Build order data
     *
     * @param OrderAdapterInterface $order
     * @param Quote                 $quote
     *
     * @return array
     */
    public function buildOrderData($order, $quote)
    {
        $currency = $order->getCurrencyCode();
        $quoteId = $quote->getId();

        if ($currency === null) {
            $currency = 'EUR';
        }

        $metadata = [
            'ID Quote' => $quoteId,
            'Order'    => $order->getOrderIncrementId(),
            'Website'  => $this->_urlBuilder->getUrl('', ['_nosid' => true]),
        ];

        $paymentTab = [
            'currency' => $currency,
            'metadata' => $metadata,
            'store_id' => $order->getStoreId(),
        ];

        return $paymentTab;
    }

    /**
     * Build payment data
     *
     * @param OrderAdapterInterface $order
     * @param InfoInterface         $payment
     * @param Quote                 $quote
     *
     * @return array
     */
    public function buildPaymentData($order, $payment, $quote)
    {
        $quoteId = $quote->getId();
        $storeId = $order->getStoreId();

        $isSandbox = $this->payplugConfig->getIsSandbox($storeId);

        $paymentData = [];
        $paymentData['notification_url'] = $this->_urlBuilder->getUrl('payplug_payments/payment/ipn', [
            'ipn_store_id' => $storeId,
            'ipn_sandbox'  => (int)$isSandbox,
            '_nosid' => true,
        ]);
        $paymentData['force_3ds'] = false;

        $paymentData['hosted_payment'] = [
            'return_url' => $this->_urlBuilder->getUrl('payplug_payments/payment/paymentReturn', [
                '_secure'  => true,
                'quote_id' => $quoteId,
                '_nosid' => true,
            ]),
            'cancel_url' => $this->_urlBuilder->getUrl('payplug_payments/payment/cancel', [
                '_secure'  => true,
                'quote_id' => $quoteId,
                '_nosid' => true,
            ]),
        ];

        return $paymentData;
    }
}
