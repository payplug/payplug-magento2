<?php

declare(strict_types=1);

namespace Payplug\Payments\Helper\Transaction;

use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\ScopeInterface;

class ApplePayBuilder extends AbstractBuilder
{
    /**
     * @inheritdoc
     */
    public function buildPaymentData(OrderInterface|OrderAdapterInterface $order, InfoInterface $payment, Quote $quote): array
    {
        $merchandDomain = parse_url($this->scopeConfig->getValue('web/secure/base_url', ScopeInterface::SCOPE_STORE), PHP_URL_HOST);

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
