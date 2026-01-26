<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Plugin\Order\Handler;

use Magento\Sales\Model\Order;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Gateway\Config\InstallmentPlan;

class State
{
    /**
     * @var Data
     */
    private $payplugHelper;

    /**
     * @param Data $payplugHelper
     */
    public function __construct(Data $payplugHelper)
    {
        $this->payplugHelper = $payplugHelper;
    }

    /**
     * Force CLOSED state for fully refunded orders linked to an aborted installment plan
     *
     * @param \Magento\Sales\Model\ResourceModel\Order\Handler\State $subject
     * @param callable                                               $proceed
     * @param Order                                                  $order
     *
     * @return \Magento\Sales\Model\ResourceModel\Order\Handler\State
     */
    public function aroundCheck(
        \Magento\Sales\Model\ResourceModel\Order\Handler\State $subject,
        callable $proceed,
        Order $order
    ) {
        $result = $proceed($order);

        if ($order->getState() !== Order::STATE_PROCESSING && $order->getState() !== Order::STATE_COMPLETE) {
            return $result;
        }

        $paymentMethod = $order->getPayment()->getMethod();
        if ($paymentMethod != InstallmentPlan::METHOD_CODE) {
            return $result;
        }

        $orderInstallmentPlan = $this->payplugHelper->getOrderInstallmentPlan($order->getIncrementId());

        if ($orderInstallmentPlan->getStatus() != \Payplug\Payments\Model\Order\InstallmentPlan::STATUS_ABORTED) {
            return $result;
        }

        if ($order->getTotalPaid() > $order->getTotalRefunded()) {
            return $result;
        }

        $order->setState(Order::STATE_CLOSED)
            ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_CLOSED));

        return $result;
    }
}
