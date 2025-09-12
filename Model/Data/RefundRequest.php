<?php

declare(strict_types=1);

namespace Payplug\Payments\Model\Data;

use Magento\Sales\Api\Data\OrderInterface;
use Payplug\Payments\Api\Data\RefundRequestInterface;

class RefundRequest implements RefundRequestInterface
{
    private OrderInterface $order;
    private string $refundId;
    private string $refundPaymentId;
    private float $refundAmount;

    /**
     * @inheritDoc
     */
    public function setOrder(OrderInterface $order): RefundRequestInterface
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getOrder(): OrderInterface
    {
        return $this->order;
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

    public function setRefundAmount(float $refundAmount): RefundRequestInterface
    {
        $this->refundAmount = $refundAmount;

        return $this;
    }

    public function getRefundAmount(): float
    {
        return $this->refundAmount;
    }
}
