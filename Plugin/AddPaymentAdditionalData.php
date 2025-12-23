<?php
/**
 * Copyright © Payplug. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

declare(strict_types=1);

namespace Payplug\Payments\Plugin;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\SalesGraphQl\Model\Order\OrderPayments;
use Payplug\Payments\Helper\Data as PayplugDataHelper;

class AddPaymentAdditionalData
{
    /**
     * @param PayplugDataHelper $payplugDataHelper
     */
    public function __construct(
        private readonly PayplugDataHelper $payplugDataHelper
    ) {
    }

    /**
     * Add payment_url to the payment method
     *
     * @param OrderPayments $subject
     * @param array $result
     * @param OrderInterface $orderModel
     * @return array
     */
    public function afterGetOrderPaymentMethod(OrderPayments $subject, array $result, OrderInterface $orderModel): array
    {
        $paymentMethodCode = $orderModel->getPayment()->getMethod();
        $isPayplugPayment = $this->payplugDataHelper->isCodePayplugPayment($paymentMethodCode);

        if ($isPayplugPayment === false) {
            return $result;
        }

        $orderPayment = $orderModel->getPayment();

        if ($orderPayment === null) {
            return $result;
        }

        $additionalInformation = $orderPayment->getAdditionalInformation();
        $paymentUrl = $additionalInformation['payment_url'] ?? null;

        if ($paymentUrl === null) {
            return $result;
        }

        $paymentMethod = reset($result);
        $paymentMethod['additional_data'][] = [
            'name' => 'payplug_payment_url',
            'value' => $paymentUrl
        ];

        return [$paymentMethod];
    }
}
