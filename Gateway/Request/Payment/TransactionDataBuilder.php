<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Gateway\Request\Payment;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order\Payment;
use Payplug\Payments\Gateway\Helper\SubjectReader;
use Payplug\Payments\Helper\Transaction\AbstractBuilder;
use Payplug\Payments\Logger\Logger as PayplugLogger;

class TransactionDataBuilder implements BuilderInterface
{
    /**
     * @param SubjectReader $subjectReader
     * @param AbstractBuilder $builder
     * @param CartRepositoryInterface $cartRepository
     * @param PayplugLogger $payplugLogger
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
        private readonly AbstractBuilder $builder,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly PayplugLogger $payplugLogger
    ) {
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);

        $order = $paymentDO->getOrder();
        $payment = $paymentDO->getPayment();
        $quote = null;

        if ($payment instanceof Payment) {
            $quoteId = $payment->getOrder()->getQuoteId();

            try {
                $quote = $this->cartRepository->get($quoteId);
            } catch (NoSuchEntityException $e) {
                $this->payplugLogger->error($e->getMessage());
            }
        }

        if ($quote === null) {
            $quote = $this->subjectReader->getQuote();
        }

        return $this->builder->buildTransaction($order, $payment, $quote);
    }
}
