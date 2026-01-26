<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Helper\Transaction;

use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Model\ScopeInterface;

class ApplePayBuilder extends AbstractBuilder
{
    /**
     * @inheritdoc
     */
    public function buildPaymentData($order, InfoInterface $payment, CartInterface $quote): array
    {
        $baseUrl = $this->scopeConfig->getValue('web/secure/base_url', ScopeInterface::SCOPE_STORE);
        $merchandDomain = $this->uriHelper->parse($baseUrl)->getHost();

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
