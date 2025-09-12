<?php

declare(strict_types=1);

namespace Payplug\Payments\Api\Data;

use Magento\Sales\Api\Data\OrderInterface;

/**
 * RefundRequest interface for message queue
 *
 * @api
 */
interface RefundRequestInterface
{
    /**
     * @param OrderInterface $order
     *
     * @return RefundRequestInterface
     * @api
     */
    public function setOrder(OrderInterface $order): RefundRequestInterface;

    /**
     * @return OrderInterface
     * @api
     */
    public function getOrder(): OrderInterface;

    /**
     * @param string $refundId
     *
     * @return RefundRequestInterface
     * @api
     */
    public function setRefundId(string $refundId): RefundRequestInterface;

    /**
     * @return string
     * @api
     */
    public function getRefundId(): string;

    /**
     * @param string $refundPaymentId
     *
     * @return RefundRequestInterface
     * @api
     */
    public function setRefundPaymentId(string $refundPaymentId): RefundRequestInterface;

    /**
     * @return string
     * @api
     */
    public function getRefundPaymentId(): string;

    /**
     * @param float $refundAmount
     *
     * @return RefundRequestInterface
     * @api
     */
    public function setRefundAmount(float $refundAmount): RefundRequestInterface;

    /**
     * @return float
     * @api
     */
    public function getRefundAmount(): float;
}
