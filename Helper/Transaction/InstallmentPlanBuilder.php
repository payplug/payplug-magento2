<?php

declare(strict_types=1);

namespace Payplug\Payments\Helper\Transaction;

use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;

class InstallmentPlanBuilder extends AbstractBuilder
{
    /**
     * @inheritdoc
     */
    public function buildAmountData(OrderInterface|OrderAdapterInterface $order): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function buildPaymentData(OrderInterface|OrderAdapterInterface $order, InfoInterface $payment, Quote $quote): array
    {
        $paymentData = parent::buildPaymentData($order, $payment, $quote);
        unset($paymentData['force_3ds']);

        $paymentData['schedule'] = $this->generateSchedule($order, $payment);

        return $paymentData;
    }

    /**
     * Generate InstallmentPlan payments schedule
     *
     * @param OrderAdapterInterface $order
     * @param InfoInterface         $payment
     *
     * @return array
     */
    private function generateSchedule($order, $payment)
    {
        $total = $order->getGrandTotalAmount() * 100;

        $schedule = [];
        $splitCount = $payment->getMethodInstance()->getConfigData('count');

        $amounts = [];
        for ($i=1; $i<=$splitCount; $i++) {
            $amounts[] = (int) ($total / $splitCount);
        }

        $amounts[0] += (int) ($total % $splitCount);

        $date = new \DateTime();
        $interval = new \DateInterval("P30D");

        foreach ($amounts as $key => $amount) {
            $date = clone $date;
            $scheduleDate = "TODAY";
            if ($key > 0) {
                $date->add($interval);
                $scheduleDate = $date->format('Y-m-d');
            }

            $schedule[] = [
                'date' => $scheduleDate,
                'amount' => $amount,
            ];
        }

        return $schedule;
    }
}
