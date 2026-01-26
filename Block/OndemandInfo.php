<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Block;

use Exception;
use Magento\Framework\Exception\LocalizedException;

class OndemandInfo extends Info
{
    /**
     * @var string
     */
    protected $_template = 'Payplug_Payments::info/ondemand.phtml';

    /**
     * Get some admin specific information in format of array($label => $value)
     *
     * @throws LocalizedException
     */
    public function getAdminSpecificInformation(): array
    {
        try {
            $orderIncrementId = $this->getInfo()->getOrder()->getIncrementId();
            $orderPayments = $this->payplugHelper->getOrderPayments($orderIncrementId);
        } catch (Exception $e) {
            $this->payplugLogger->error($e->getMessage());
            return [];
        }

        if (count($orderPayments) === 0) {
            return [];
        }

        $order = $this->getInfo()->getOrder();

        $ondemandInfo = [
            'payments' => [],
        ];

        foreach ($orderPayments as $orderPayment) {
            try {
                $payment = $orderPayment->retrieve($orderPayment->getScopeId($order), $orderPayment->getScope($order));
                $paymentInfoDetails = $this->buildPaymentDetails($payment, $order);

                $paymentInfo = [
                    'date' => $paymentInfoDetails['Paid at'],
                    'amount' => $paymentInfoDetails['Amount'],
                    'status' => $paymentInfoDetails['Status'],
                    'details' => $paymentInfoDetails,
                ];
                if ($payment->failure !== null && $payment->failure->code === 'aborted') {
                    $paymentInfo['status'] = __('Aborted payment');
                }

                $ondemandInfo['payments'][] = $paymentInfo;
            } catch (Exception $e) {
                $this->payplugLogger->error($e->getMessage());
            }
        }

        return $ondemandInfo;
    }
}
