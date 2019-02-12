<?php

namespace Payplug\Payments\Block;

use Magento\Framework\Exception\NoSuchEntityException;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\Payment\AbstractPaymentMethod;

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
            $orderId = $this->getInfo()->getOrder()->getId();
            $orderPayment = $this->payplugHelper->getOrderPayment($orderId);
        } catch (NoSuchEntityException $e) {
            return [];
        }

        if (!$orderPayment->getId()) {
            return [];
        }

        $isSandbox = $orderPayment->isSandbox();
        $environmentMode = AbstractPaymentMethod::ENVIRONMENT_TEST;
        if (!$isSandbox) {
            $environmentMode = AbstractPaymentMethod::ENVIRONMENT_LIVE;
        }

        $paymentId = $orderPayment->getPaymentId();
        $order = $this->getInfo()->getOrder();

        try {
            $payment = $orderPayment->retrieve($paymentId, $environmentMode, $order->getStoreId());
        } catch (PayplugException $e) {
            $this->payplugLogger->error($e->__toString());
            return [];
        } catch (\Exception $e) {
            $this->payplugLogger->error($e->getMessage());
            return [];
        }

        $status = __('Not Paid');
        if ($payment->is_refunded) {
            $status = __('Refunded');
        } elseif ($payment->amount_refunded > 0) {
            $status = __('Partially Refunded');
        } elseif ($payment->is_paid) {
            $status = __('Paid');
        }

        $amount = $order->getOrderCurrency()->formatPrecision((float)($payment->amount / 100), 2, [], false, false);

        $cardType = __('Other');
        if (in_array(strtolower($payment->card->brand), ['visa', 'mastercard', 'maestro'])) {
            $cardType = $payment->card->brand;
        } elseif (strtolower($payment->card->brand) == 'carte_bancaire') {
            $cardType = __('CB');
        }

        $country = __('n/c');
        if ($payment->card->country !== null) {
            $country = $payment->card->country;
        }

        $cardMask = __('n/c');
        if ($payment->card->last4 !== null) {
            $cardMask = '**** **** **** ' . (string) $payment->card->last4;
        }

        $expirationDate = __('n/c');
        if ($payment->card->exp_month !== null) {
            $expirationDate = date('m/y', strtotime('01.'.$payment->card->exp_month.'.'.$payment->card->exp_year));
        }

        $paymentInfo = [
            'Payplug Payment ID' => $payment->id,
            'Status' => $status,
            'Amount' => $amount,
            'Paid at' => date('d/m/Y H:i', $payment->created_at),
            'Credit card' => $cardType . ' (' . $country . ')',
            'Card mask' => $cardMask,
            '3-D Secure' => $payment->is_3ds ? __('Yes') : __('No'),
            'Expiration Date' => $expirationDate,
            'Mode' => $payment->is_live ? __('Live') : __('Test'),
        ];

        return $paymentInfo;
    }
}
