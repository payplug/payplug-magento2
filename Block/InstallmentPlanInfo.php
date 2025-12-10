<?php

declare(strict_types=1);

namespace Payplug\Payments\Block;

use Exception;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template\Context;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\OrderInstallmentPlanRepository;
use Payplug\Payments\Model\OrderPaymentRepository;
use Throwable;

class InstallmentPlanInfo extends Info
{
    /**
     * @var string
     */
    protected $_template = 'Payplug_Payments::info/installment_plan.phtml';

    /**
     * @param OrderPaymentRepository $orderPaymentRepository
     * @param OrderInstallmentPlanRepository $orderInstallmentPaymentRepository
     * @param FormKey $formKey
     * @param Context $context
     * @param Data $payplugHelper
     * @param Logger $payplugLogger
     * @param array $data
     */
    public function __construct(
        private readonly OrderPaymentRepository $orderPaymentRepository,
        private readonly OrderInstallmentPlanRepository $orderInstallmentPaymentRepository,
        private readonly FormKey $formKey,
        Context $context,
        Data $payplugHelper,
        Logger $payplugLogger,
        array $data = []
    ) {
        parent::__construct($context, $payplugHelper, $payplugLogger, $data);
    }

    /**
     * Get the form key
     *
     * @throws LocalizedException
     */
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
        } catch (Throwable) {
            return [];
        }

        if (!$orderInstallmentPlan->getId()) {
            return [];
        }

        try {
            $order = $this->getInfo()->getOrder();
            $orderPayment = $this->orderInstallmentPaymentRepository->get($orderIncrementId, 'order_id');
            $installmentPlan = $orderInstallmentPlan->retrieve(
                $orderPayment->getScopeId($order),
                $orderPayment->getScope($order)
            );
        } catch (PayplugException $e) {
            $this->payplugLogger->error($e->__toString());
            return [];
        } catch (Exception $e) {
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
                    $payment = $orderPayment->retrieve(
                        $orderPayment->getScopeId($order),
                        $orderPayment->getScope($order)
                    );
                    $paymentInfo['details'] = $this->buildPaymentDetails($payment, $order);
                    $paymentInfo['status'] = $paymentInfo['details']['Status'];
                } catch (NoSuchEntityException) {
                    $this->payplugLogger->error(sprintf('Order Payment with id %s not found', $paymentId));
                }
            }

            $installmentPlanInfo['payments'][] = $paymentInfo;
        }

        return $installmentPlanInfo;
    }
}
