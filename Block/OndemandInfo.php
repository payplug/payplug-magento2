<?php

namespace Payplug\Payments\Block;

use Magento\Framework\Exception\NoSuchEntityException;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\OrderPaymentRepository;

class OndemandInfo extends Info
{
    /**
     * @var string
     */
    protected $_template = 'Payplug_Payments::info/ondemand.phtml';

    /**
     * @var OrderPaymentRepository
     */
    private $orderPaymentRepository;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param Data                                             $payplugHelper
     * @param Logger                                           $payplugLogger
     * @param OrderPaymentRepository                           $orderPaymentRepository
     * @param array                                            $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        Data $payplugHelper,
        Logger $payplugLogger,
        OrderPaymentRepository $orderPaymentRepository,
        array $data = []
    ) {
        parent::__construct($context, $payplugHelper, $payplugLogger, $data);

        $this->orderPaymentRepository = $orderPaymentRepository;
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
            $orderPayments = $this->payplugHelper->getOrderPayments($orderIncrementId);
        } catch (\Exception $e) {
            $this->payplugLogger->error($e->getMessage());
            return [];
        }

        if (count($orderPayments) === 0) {
            return [];
        }

        $order = $this->getInfo()->getOrder();

        $ondemandInfo = [
            'payments' => [],
        ];

        foreach ($orderPayments as $orderPayment) {
            try {
                $payment = $orderPayment->retrieve($order->getStoreId());
                $paymentInfoDetails = $this->buildPaymentDetails($payment, $order);

                $paymentInfo = [
                    'date' => $paymentInfoDetails['Paid at'],
                    'amount' => $paymentInfoDetails['Amount'],
                    'status' => $paymentInfoDetails['Status'],
                    'details' => $paymentInfoDetails,
                ];
                if ($payment->failure !== null && $payment->failure->code === 'aborted') {
                    $paymentInfo['status'] = __('Aborted payment');
                }

                $ondemandInfo['payments'][] = $paymentInfo;
            } catch (\Exception $e) {
                $this->payplugLogger->error($e->getMessage());
            }
        }

        return $ondemandInfo;
    }
}
