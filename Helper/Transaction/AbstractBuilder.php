<?php

declare(strict_types=1);

namespace Payplug\Payments\Helper\Transaction;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Data\Form\FormKey;
use Magento\Payment\Gateway\Data\AddressAdapterInterface;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Address;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Helper\Country;
use Payplug\Payments\Helper\Phone;
use Payplug\Payments\Logger\Logger;

abstract class AbstractBuilder extends AbstractHelper
{
    public function __construct(
        Context $context,
        protected Config $payplugConfig,
        protected Country $countryHelper,
        protected Phone $phoneHelper,
        protected Logger $logger,
        protected FormKey $formKey
    ) {
        parent::__construct($context);
    }

    /**
     * Build PayPlug payment transaction
     *
     * @param OrderInterface|OrderAdapterInterface $order
     * @param InfoInterface $payment
     * @param CartInterface|Quote $quote
     *
     * @return array
     */
    public function buildTransaction($order, InfoInterface $payment, $quote): array
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
     * @param OrderInterface|OrderAdapterInterface $order
     *
     * @return int[]
     */
    public function buildAmountData($order): array
    {
        $unroundedAmount = $order?->getGrandTotalAmount() ?? $order?->getBaseGrandTotal() ?? 0;

        $paymentTab = [
            'amount' => (int) round($unroundedAmount * 100),
        ];

        return $paymentTab;
    }

    /**
     * Build customer data
     *
     * @param OrderInterface|OrderAdapterInterface $order
     * @param InfoInterface $payment
     * @param CartInterface|Quote $quote
     *
     * @return array
     */
    public function buildCustomerData($order, InfoInterface $payment, $quote): array
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
     */
    private function buildAddressData(object $address, string $language, array $allowedCountries, string $defaultCountry): array
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
     * @param OrderInterface|OrderAdapterInterface $order
     * @param CartInterface|Quote $quote
     *
     * @return array
     */
    public function buildOrderData($order, $quote): array
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
     * @param OrderInterface|OrderAdapterInterface $order
     * @param InfoInterface $payment
     * @param CartInterface $quote
     *
     * @return array
     */
    public function buildPaymentData($order, InfoInterface $payment, CartInterface $quote): array
    {
        $quoteId = $quote->getId();
        $storeId = $order->getStoreId();

        $isSandbox = $this->payplugConfig->getIsSandbox((int)$storeId);

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
                'form_key' => $this->formKey->getFormKey() ?: ''
            ]),
        ];

        return $paymentData;
    }
}
