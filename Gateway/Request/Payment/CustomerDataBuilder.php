<?php

namespace Payplug\Payments\Gateway\Request\Payment;

use Magento\Payment\Gateway\Data\AddressAdapterInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Helper\Country;
use Payplug\Payments\Helper\Phone;

class CustomerDataBuilder implements BuilderInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var Config
     */
    private $payplugConfig;

    /**
     * @var Country
     */
    private $countryHelper;

    /**
     * @var Phone
     */
    private $phoneHelper;

    /**
     * Constructor
     *
     * @param SubjectReader $subjectReader
     * @param Config        $payplugConfig
     * @param Country       $countryHelper
     * @param Phone         $phoneHelper
     */
    public function __construct(
        SubjectReader $subjectReader,
        Config $payplugConfig,
        Country $countryHelper,
        Phone $phoneHelper
    ) {
        $this->subjectReader = $subjectReader;
        $this->payplugConfig = $payplugConfig;
        $this->countryHelper = $countryHelper;
        $this->phoneHelper = $phoneHelper;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject)
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);

        $order = $paymentDO->getOrder();
        $payment = $paymentDO->getPayment();

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
            $quote = $this->subjectReader->getQuote();
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
     * @param AddressAdapterInterface $address
     * @param string                  $language
     * @param array                   $allowedCountries
     * @param string                  $defaultCountry
     *
     * @return array
     */
    private function buildAddressData(AddressAdapterInterface $address, $language, $allowedCountries, $defaultCountry)
    {
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

        $prefix = strtolower($address->getPrefix());
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
            'address1' => $address->getStreetLine1(),
            'address2' => $address->getStreetLine2() ?: null,
            'postcode' => $address->getPostcode(),
            'city' => $address->getCity(),
            'state' => $address->getRegionCode() ?: null,
            'country' => $country,
            'language' => $language,
        ];
    }
}
