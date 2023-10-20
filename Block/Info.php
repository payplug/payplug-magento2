<?php

namespace Payplug\Payments\Block;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;

class Info extends \Magento\Payment\Block\Info
{
    /**
     * @var string
     */
    protected $_template = 'Payplug_Payments::info/default.phtml';

    /**
     * @var Data
     */
    protected $payplugHelper;

    /**
     * @var Logger
     */
    protected $payplugLogger;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param Data                                             $payplugHelper
     * @param Logger                                           $payplugLogger
     * @param array                                            $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        Data $payplugHelper,
        Logger $payplugLogger,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->payplugHelper = $payplugHelper;
        $this->payplugLogger = $payplugLogger;
    }

    /**
     * Get some admin specific information in format of array($label => $value)
     *
     * @return array
     */
    public function getAdminSpecificInformation()
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
     * @param \Payplug\Resource\Payment $payment
     * @param Order                     $order
     *
     * @return array
     */
    protected function buildPaymentDetails($payment, $order)
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
