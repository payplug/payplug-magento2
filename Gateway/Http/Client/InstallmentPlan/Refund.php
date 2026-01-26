<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Gateway\Http\Client\InstallmentPlan;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\Order\InstallmentPlan;
use Payplug\Payments\Model\OrderPaymentRepository;

class Refund implements ClientInterface
{
    /**
     * @var OrderPaymentRepository
     */
    private $orderPaymentRepository;

    /**
     * @var Logger
     */
    private $payplugLogger;

    /**
     * @param OrderPaymentRepository $orderPaymentRepository
     * @param Logger                 $payplugLogger
     */
    public function __construct(OrderPaymentRepository $orderPaymentRepository, Logger $payplugLogger)
    {
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->payplugLogger = $payplugLogger;
    }

    /**
     * Place Refund request
     *
     * @param TransferInterface $transferObject
     *
     * @return array
     *
     * @throws \Exception
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $data = $transferObject->getBody();

        try {
            /** @var InstallmentPlan $orderInstallmentPlan */
            $orderInstallmentPlan = $data['installment_plan'];
            $metadata = ['reason' => "Refunded with Magento."];
            $storeId = $data['store_id'];
            $amount = $data['amount'] * 100;

            $installmentPlan = $orderInstallmentPlan->retrieve((int)$storeId);

            $schedules = $installmentPlan->schedule;
            $refunds = [];
            foreach ($schedules as $schedule) {
                if ($amount == 0) {
                    break;
                }
                if (!empty($schedule->payment_ids) && is_array($schedule->payment_ids)) {
                    $paymentId = $schedule->payment_ids[0];

                    try {
                        $orderPayment = $this->orderPaymentRepository->get($paymentId, 'payment_id');
                    } catch (NoSuchEntityException $e) {
                        continue;
                    }

                    $payment = $orderPayment->retrieve((int)$storeId);
                    $paymentAmount = $payment->amount - $payment->amount_refunded;

                    $amountToRefund = $amount;
                    if ($amountToRefund > $paymentAmount) {
                        $amountToRefund = $paymentAmount;
                    }
                    $amount -= $amountToRefund;

                    $refunds[] = [
                        'orderPayment' => $orderPayment,
                        'amount' => $amountToRefund / 100,
                    ];
                }
            }

            if ($amount > 0) {
                throw new \Exception(__('Amount remaining in payplug is inferior to amount to refund'));
            }

            foreach ($refunds as $refund) {
                $refund['orderPayment']->makeRefund((float)$refund['amount'], $metadata, (int)$storeId);
            }

            return [];
        } catch (NoSuchEntityException $e) {
            throw new \Exception(__('Could not find valid payplug order payment. Please try refunding offline.'));
        } catch (PayplugException $e) {
            $this->payplugLogger->error($e->__toString());
            throw new \Exception(__('Error while refunding online. Please try again or contact us.'));
        } catch (\Exception $e) {
            $this->payplugLogger->error($e->getMessage());
            throw new \Exception(__('Error while refunding online. Please try again or contact us.'));
        }
    }
}
