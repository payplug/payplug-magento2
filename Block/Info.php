<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Block;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Info as BaseInfo;
use Magento\Sales\Api\Data\OrderInterface;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Resource\Payment;

class Info extends BaseInfo
{
    /**
     * @var string
     */
    protected $_template = 'Payplug_Payments::info/default.phtml';

    /**
     * @param Context $context
     * @param Data $payplugHelper
     * @param Logger $payplugLogger
     * @param array $data
     */
    public function __construct(
        Context $context,
        protected Data $payplugHelper,
        protected Logger $payplugLogger,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     *  Get some admin specific information in format of array($label => $value)
     *
     * @return array
     * @throws LocalizedException
     */
    public function getAdminSpecificInformation(): array
    {
        try {
            $orderIncrementId = $this->getInfo()->getOrder()->getIncrementId();
            $orderPayment = $this->payplugHelper->getOrderPayment($orderIncrementId);
        } catch (NoSuchEntityException $e) {
            return [];
        }

        if (!$orderPayment->getId()) {
            return [];
        }

        $order = $this->getInfo()->getOrder();

        try {
            $payment = $orderPayment->retrieve($orderPayment->getScopeId($order), $orderPayment->getScope($order));
        } catch (PayplugException $e) {
            $this->payplugLogger->error($e->__toString());
            return [];
        } catch (\Exception $e) {
            $this->payplugLogger->error($e->getMessage());
            return [];
        }

        return $this->buildPaymentDetails($payment, $order);
    }

    /**
     * Get PayPlug payment details
     *
     * @param Payment $payment
     * @param OrderInterface $order
     *
     * @return array
     */
    protected function buildPaymentDetails(Payment $payment, OrderInterface $order): array
    {
        $status = __('Not Paid');

        if ($payment->is_refunded) {
            $status = __('Refunded');
        } elseif ($payment->amount_refunded > 0) {
            $status = __('Partially Refunded');
        } elseif ($payment->is_paid) {
            $status = __('Paid');
        } elseif ($payment->authorization?->authorized_at) {
            $status = __('Authorized');
        }

        if ($this->payplugHelper->isCodePayplugPaymentPpro($order->getPayment()->getMethod())) {
            $methodLines = [
                'Payplug Payment Method' => __('SEPA Credit Transfer'),
            ];
        } else {
            $cardMask = (string) $payment->card->last4 ?? '';
            $cardExpMonth = (string) $payment->card->exp_month ?? '';
            $cardExpYear = (string) $payment->card->exp_year ?? '';
            $cardBrand = (string) $payment->card->brand ?? '';
            $cardCountry = (string) $payment->card->country ?? '';
            $is3DS = (bool) $payment->is_3ds ?? false;

            if (in_array(strtolower($cardBrand), ['carte_bancaire', 'cb'])) {
                $cardBrand = 'CB';
            }

            if ($cardExpMonth !== '' && $cardExpYear !== '') {
                $expirationDate = $cardExpMonth . '/' . $cardExpYear;
            } else {
                $expirationDate = 'n/c';
            }

            $methodLines = [
                'Credit card' => ($cardBrand ?: __('Other')) . ' (' . ($cardCountry ?: 'n/c') . ')',
                'Card mask' => $cardMask ? '**** **** **** ' . $cardMask : 'n/c',
                'Expiration Date' => $expirationDate,
                '3-D Secure' => $is3DS === true ? __('Yes') : __('No'),
            ];
        }

        $amount = $order->getOrderCurrency()->formatPrecision((float)($payment->amount / 100), 2, [], false);

        return array_merge([
            'Payplug Payment ID' => $payment->id,
            'Status' => $status,
            'Amount' => $amount,
            'Created on' => $payment->created_at ? date('d/m/Y H:i', $payment->created_at) : 'n/c',
            'Paid at' => $payment->paid_at ? date('d/m/Y H:i', $payment->paid_at) : 'n/c'
        ], $methodLines, [
            'Mode' => $payment->is_live === false ? __('PayPlug TEST mode') : __('PayPlug LIVE mode'),
        ]);
    }
}
