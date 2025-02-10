<?php

declare(strict_types=1);

namespace Payplug\Payments\Block;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;

class OndemandInfo extends Info
{
    public function __construct(
        Context $context,
        Data $payplugHelper,
        Logger $payplugLogger,
        array $data = []
    ) {
        parent::__construct($context, $payplugHelper, $payplugLogger, $data);
    }

    /**
     * Get some admin specific information in format of array($label => $value)
     *
     * @return array
     * @throws LocalizedException
     */
    public function getAdminSpecificInformation(): array
    {
        try {
            $orderIncrementId = $this->getInfo()->getOrder()->getIncrementId();
            $orderPayments = $this->payplugHelper->getOrderPayments($orderIncrementId);
        } catch (\Exception $e) {
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
            } catch (\Exception $e) {
                $this->payplugLogger->error($e->getMessage());
            }
        }

        return $ondemandInfo;
    }
}
