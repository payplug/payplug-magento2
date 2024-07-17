<?php

declare(strict_types=1);

namespace Payplug\Payments\Block;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Info as BaseInfo;
use Magento\Sales\Model\Order;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Resource\Payment;

class Info extends BaseInfo
{
    protected $_template = 'Payplug_Payments::info/default.phtml';

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
            $payment = $orderPayment->retrieve($order->getStoreId());
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
     * @param Order $order
     *
     * @return array
     */
    protected function buildPaymentDetails(Payment $payment, Order $order): array
    {
        $status = __('Not Paid');
        if ($payment->is_refunded) {
            $status = __('Refunded');
        } elseif ($payment->amount_refunded > 0) {
            $status = __('Partially Refunded');
        } elseif ($payment->is_paid) {
            $status = __('Paid');
        }

        $amount = $order->getOrderCurrency()->formatPrecision((float)($payment->amount / 100), 2, [], false, false);

        if ($this->payplugHelper->isCodePayplugPaymentPpro($order->getPayment()->getMethod())) {
            $methodLines = [
                'Payplug Payment Method' => __('SEPA Credit Transfer'),
            ];
        } else {
            $cardType = __('Other');
            if (in_array(strtolower($payment->card->brand ?? ''), ['visa', 'mastercard', 'maestro'])) {
                $cardType = $payment->card->brand;
            } elseif (strtolower($payment->card->brand ?? '') == 'carte_bancaire') {
                $cardType = __('CB');
            }

            $country = __('n/c');
            if ($payment->card->country !== null) {
                $country = $payment->card->country;
            }

            $cardMask = __('n/c');
            if ($payment->card->last4 !== null) {
                $cardMask = '**** **** **** ' . (string)$payment->card->last4;
            }

            $expirationDate = __('n/c');
            if ($payment->card->exp_month !== null) {
                $expirationDate = date('m/y', strtotime('01.'.$payment->card->exp_month.'.'.$payment->card->exp_year));
            }

            $methodLines = [
                'Credit card' => $cardType . ' (' . $country . ')',
                'Card mask' => $cardMask,
                '3-D Secure' => $payment->is_3ds ? __('Yes') : __('No'),
                'Expiration Date' => $expirationDate,
            ];
        }

        return array_merge([
            'Payplug Payment ID' => $payment->id,
            'Status' => $status,
            'Amount' => $amount,
            'Paid at' => date('d/m/Y H:i', $payment->created_at),
        ], $methodLines, [
            'Mode' => $payment->is_live ? __('PayPlug LIVE mode') : __('PayPlug TEST mode'),
        ]);
    }
}
