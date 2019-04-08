<?php

namespace Payplug\Payments\Gateway\Request\Payment;

use Payplug\Payments\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class CustomerDataBuilder implements BuilderInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * Constructor
     *
     * @param SubjectReader $subjectReader
     */
    public function __construct(SubjectReader $subjectReader)
    {
        $this->subjectReader = $subjectReader;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject)
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);

        $order = $paymentDO->getOrder();

        $address = null;
        if ($order->getShippingAddress() !== null) {
            $address = $order->getShippingAddress();
        } elseif ($order->getBillingAddress() !== null) {
            $address = $order->getBillingAddress();
        }

        $addressStreet    = 'no data';
        $addressStreet2   = null;
        $addressPostcode  = '00000';
        $addressCity      = 'no data';
        $addressCountryId = 'FR';
        if ($address !== null) {
            $street1 = $address->getStreetLine1();
            if (!empty($street1)) {
                $addressStreet = $street1;
            }
            $street2 = $address->getStreetLine2();
            if (!empty($street2)) {
                $addressStreet2 = $street2;
            }
            $addressPostcode = $address->getPostcode();
            $addressCity = $address->getCity();
            $addressCountryId = $address->getCountryId();
        }

        $firstname = '';
        $lastname = '';
        $email = '';
        if ($order->getBillingAddress() !== null) {
            $firstname = $order->getBillingAddress()->getFirstname();
            $lastname = $order->getBillingAddress()->getLastname();
            $email = $order->getBillingAddress()->getEmail();
        }

        return [
            'customer' => [
                'first_name' => $firstname,
                'last_name' => $lastname,
                'email' => $email,
                'address1' => $addressStreet,
                'address2' => $addressStreet2,
                'postcode' => $addressPostcode,
                'city' => $addressCity,
                'country' => $addressCountryId,
            ]
        ];
    }
}
