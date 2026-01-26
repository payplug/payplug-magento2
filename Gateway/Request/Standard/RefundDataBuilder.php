<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Gateway\Request\Standard;

use Magento\Framework\Exception\PaymentException;
use Payplug\Payments\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Payplug\Payments\Helper\Data;

class RefundDataBuilder implements BuilderInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var Data
     */
    private $payplugHelper;

    /**
     * Constructor
     *
     * @param SubjectReader $subjectReader
     * @param Data          $payplugHelper
     */
    public function __construct(SubjectReader $subjectReader, Data $payplugHelper)
    {
        $this->subjectReader = $subjectReader;
        $this->payplugHelper = $payplugHelper;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject)
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);

        $order = $paymentDO->getOrder();

        $payplugPayment = $this->payplugHelper->getOrderLastPayment($order->getOrderIncrementId());
        if ($payplugPayment === null) {
            throw new PaymentException(__('Unable to find payment linked to order %1', $order->getOrderIncrementId()));
        }

        return [
            'payment' => $payplugPayment,
            'store_id' => $order->getStoreId(),
            'amount' => $this->subjectReader->readAmount($buildSubject),
        ];
    }
}
