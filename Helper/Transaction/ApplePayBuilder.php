<?php

namespace Payplug\Payments\Helper\Transaction;

class ApplePayBuilder extends AbstractBuilder
{
    /**
     * @inheritdoc
     */
    public function buildPaymentData($order, $payment, $quote)
    {
        $merchandDomain = parse_url($this->scopeConfig->getValue('web/secure/base_url'), PHP_URL_HOST);

        $paymentData = parent::buildPaymentData($order, $payment, $quote);
        $paymentData['payment_method'] = 'apple_pay';
        $paymentData['payment_context'] = [
            'apple_pay' => [
                'domain_name' => $merchandDomain,
                'application_data' => base64_encode(json_encode([
                    'apple_pay_domain' => $merchandDomain,
                ])),
            ],
        ];

        return $paymentData;
    }
}
