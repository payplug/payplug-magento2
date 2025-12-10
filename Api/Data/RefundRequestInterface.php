<?php

declare(strict_types=1);

namespace Payplug\Payments\Api\Data;

/**
 * RefundRequest interface for message queue
 *
 * @api
 */
interface RefundRequestInterface
{
    /**
     * Set order ID
     *
     * @param int $orderId
     * @return RefundRequestInterface
     * @api
     */
    public function setOrderId(int $orderId): RefundRequestInterface;

    /**
     * Get order ID
     *
     * @return int
     * @api
     */
    public function getOrderId(): int;

    /**
     * Set refund ID
     *
     * @param string $refundId
     *
     * @return RefundRequestInterface
     * @api
     */
    public function setRefundId(string $refundId): RefundRequestInterface;

    /**
     * Get refund ID
     *
     * @return string
     * @api
     */
    public function getRefundId(): string;

    /**
     * Set refund payment ID
     *
     * @param string $refundPaymentId
     *
     * @return RefundRequestInterface
     * @api
     */
    public function setRefundPaymentId(string $refundPaymentId): RefundRequestInterface;

    /**
     * Get refund payment ID
     *
     * @return string
     * @api
     */
    public function getRefundPaymentId(): string;

    /**
     * Set refund amount
     *
     * @param float $refundAmount
     *
     * @return RefundRequestInterface
     * @api
     */
    public function setRefundAmount(float $refundAmount): RefundRequestInterface;

    /**
     * Get refund amount
     *
     * @return float
     * @api
     */
    public function getRefundAmount(): float;
}
