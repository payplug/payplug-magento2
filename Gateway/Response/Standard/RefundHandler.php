<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Gateway\Response\Standard;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Payplug\Exception\UndefinedAttributeException;
use Payplug\Payments\Logger\Logger as PayplugLogger;
use Payplug\Resource\Refund;

class RefundHandler implements HandlerInterface
{
    /**
     * @param PayplugLogger $payplugLogger
     */
    public function __construct(
        private readonly PayplugLogger $payplugLogger
    ) {
    }

    /**
     * Handle response
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentData = $handlingSubject['payment'] ?? null;
        $refund = $response['payment'] ?? null;

        if (!$paymentData instanceof PaymentDataObjectInterface || !$refund instanceof Refund) {
            return;
        }

        $payment = $paymentData->getPayment();

        if (!$payment instanceof OrderPayment) {
            return;
        }

        try {
            $transactionId = $refund->__get('id');
            $payment->setTransactionId($transactionId);
        } catch (UndefinedAttributeException $e) {
            $this->payplugLogger->error($e->getMessage());
        }
    }
}
