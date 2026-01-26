<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Model\Data;

use Payplug\Payments\Api\Data\RefundRequestInterface;

class RefundRequest implements RefundRequestInterface
{
    /**
     * @var int
     */
    private int $orderId;
    /**
     * @var string
     */
    private string $refundId;
    /**
     * @var string
     */
    private string $refundPaymentId;
    /**
     * @var float
     */
    private float $refundAmount;

    /**
     * @inheritDoc
     */
    public function setOrderId(int $orderId): RefundRequestInterface
    {
        $this->orderId = $orderId;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getOrderId(): int
    {
        return $this->orderId;
    }

    /**
     * @inheritDoc
     */
    public function setRefundId(string $refundId): RefundRequestInterface
    {
        $this->refundId = $refundId;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getRefundId(): string
    {
        return $this->refundId;
    }

    /**
     * @inheritDoc
     */
    public function setRefundPaymentId(string $refundPaymentId): RefundRequestInterface
    {
        $this->refundPaymentId = $refundPaymentId;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getRefundPaymentId(): string
    {
        return $this->refundPaymentId;
    }

    /**
     * Set refund amount
     *
     * @param float $refundAmount
     * @return RefundRequestInterface
     */
    public function setRefundAmount(float $refundAmount): RefundRequestInterface
    {
        $this->refundAmount = $refundAmount;

        return $this;
    }

    /**
     * Get refund amount
     *
     * @return float
     */
    public function getRefundAmount(): float
    {
        return $this->refundAmount;
    }
}
