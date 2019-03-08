<?php

namespace Payplug\Payments\Block;

use Magento\Framework\Exception\NoSuchEntityException;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\OrderPaymentRepository;

class InstallmentPlanInfo extends Info
{
    /**
     * @var string
     */
    protected $_template = 'Payplug_Payments::info/installment_plan.phtml';

    /**
     * @var OrderPaymentRepository
     */
    private $orderPaymentRepository;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param Data                                             $payplugHelper
     * @param Logger                                           $payplugLogger
     * @param OrderPaymentRepository                           $orderPaymentRepository
     * @param array                                            $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        Data $payplugHelper,
        Logger $payplugLogger,
        OrderPaymentRepository $orderPaymentRepository,
        array $data = []
    ) {
        parent::__construct($context, $payplugHelper, $payplugLogger, $data);
        $this->orderPaymentRepository = $orderPaymentRepository;
    }

    /**
     * Get some admin specific information in format of array($label => $value)
     *
     * @return array
     */
    public function getAdminSpecificInformation()
    {
        try {
            $orderIncrementId = $this->getInfo()->getOrder()->getIncrementId();
            $orderInstallmentPlan = $this->payplugHelper->getOrderInstallmentPlan($orderIncrementId);
        } catch (NoSuchEntityException $e) {
            return [];
        }

        if (!$orderInstallmentPlan->getId()) {
            return [];
        }

        $order = $this->getInfo()->getOrder();

        try {
            $installmentPlan = $orderInstallmentPlan->retrieve($order->getStoreId());
        } catch (PayplugException $e) {
            $this->payplugLogger->error($e->__toString());
            return [];
        } catch (\Exception $e) {
            $this->payplugLogger->error($e->getMessage());
            return [];
        }

        $status = 'N/A';
        $labels = $this->payplugHelper->getInstallmentPlanStatusesLabel();
        if (isset($labels[$orderInstallmentPlan->getStatus()])) {
            $status = $labels[$orderInstallmentPlan->getStatus()];
        }

        $installmentPlanInfo = [
            'status' => sprintf(
                __('This order is subjected to an installment plan, whose status is <strong>%s</strong>'),
                __($status)
            ),
            'installment_plan_id' => $orderInstallmentPlan->getInstallmentPlanId(),
            'payments' => [],
            'can_abort' => $installmentPlan->is_active && !$installmentPlan->is_fully_paid,
        ];

        $schedules = $installmentPlan->schedule;
        foreach ($schedules as $schedule) {
            $amount = $schedule->amount / 100;

            $status = __('Upcoming');
            if (!$installmentPlan->is_active && !$installmentPlan->is_fully_paid) {
                $status = __('Suspended');
            }

            $paymentInfo = [
                'date' => $schedule->date,
                'amount' => $order->getOrderCurrency()->formatPrecision((float) $amount, 2, [], false, false),
                'status' => $status,
                'details' => [],
            ];

            if (!empty($schedule->payment_ids) && is_array($schedule->payment_ids)) {
                $paymentId = $schedule->payment_ids[0];

                try {
                    $orderPayment = $this->orderPaymentRepository->get($paymentId, 'payment_id');
                    $payment = $orderPayment->retrieve($order->getStoreId());
                    $paymentInfo['details'] = $this->buildPaymentDetails($payment, $order);
                    $paymentInfo['status'] = $paymentInfo['details']['Status'];
                } catch (NoSuchEntityException $e) {
                }
            }

            $installmentPlanInfo['payments'][] = $paymentInfo;
        }

        return $installmentPlanInfo;
    }
}
