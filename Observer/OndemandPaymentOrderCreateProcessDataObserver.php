<?php

namespace Payplug\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Payplug\Payments\Gateway\Config\Ondemand;

class OndemandPaymentOrderCreateProcessDataObserver implements ObserverInterface
{
    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\AdminOrder\Create $orderCreateModel */
        $orderCreateModel = $observer->getData('order_create_model');
        $requestParams = $observer->getData('request');

        if (!isset($requestParams['payment'])) {
            return;
        }

        $paymentData = $requestParams['payment'];
        if (empty($paymentData)) {
            return;
        }
        if (!isset($paymentData['method'])) {
            return;
        }

        $payment = $orderCreateModel->getQuote()->getPayment();
        $additionalInfo = $payment->getAdditionalInformation();

        $ondemandData = [
            'sent_by',
            'sent_by_value',
            'language',
            'description',
        ];
        foreach ($ondemandData as $key) {
            if ($paymentData['method'] === Ondemand::METHOD_CODE) {
                if (isset($paymentData[$key])) {
                    $additionalInfo[$key] = $paymentData[$key];
                }
            } elseif (isset($additionalInfo[$key])) {
                unset($additionalInfo[$key]);
            }
        }
        $payment->setAdditionalInformation($additionalInfo);
    }
}
