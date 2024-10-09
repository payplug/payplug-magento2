<?php

declare(strict_types=1);

namespace Payplug\Payments\Block;

use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template\Context;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\OrderPaymentRepository;

class InstallmentPlanInfo extends Info
{
    public function __construct(
        Context $context,
        Data $payplugHelper,
        Logger $payplugLogger,
        private OrderPaymentRepository $orderPaymentRepository,
        private FormKey $formKey,
        array $data = []
    ) {
        parent::__construct($context, $payplugHelper, $payplugLogger, $data);
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey() ?: '';
    }

    /**
     * Get some admin specific information in format of array($label => $value)
     */
    public function getAdminSpecificInformation(): array
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
                (string)__('This order is subjected to an installment plan, whose status is <strong>%s</strong>'),
                (string)__($status)
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
