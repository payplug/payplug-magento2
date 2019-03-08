<?php

namespace Payplug\Payments\Gateway\Request\Payment;

use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\InfoInterface;
use Payplug\Payments\Gateway\Helper\SubjectReader;

class ScheduleDataBuilder implements BuilderInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
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
        $payment = $paymentDO->getPayment();

        $paymentTab = [
            'schedule' => $this->generateSchedule($order, $payment),
        ];

        return $paymentTab;
    }

    /**
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
