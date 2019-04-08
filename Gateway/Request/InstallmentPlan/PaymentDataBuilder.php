<?php

namespace Payplug\Payments\Gateway\Request\InstallmentPlan;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Gateway\Helper\SubjectReader;
use Payplug\Payments\Helper\Config;

class PaymentDataBuilder implements BuilderInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var Config
     */
    private $payplugConfig;

    /**
     * Constructor
     *
     * @param SubjectReader $subjectReader
     * @param UrlInterface  $urlBuilder
     * @param Config        $payplugConfig
     */
    public function __construct(
        SubjectReader $subjectReader,
        UrlInterface $urlBuilder,
        Config $payplugConfig
    ) {
        $this->subjectReader = $subjectReader;
        $this->urlBuilder = $urlBuilder;
        $this->payplugConfig = $payplugConfig;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject)
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);

        $order = $paymentDO->getOrder();
        $quoteId = $this->subjectReader->getQuote()->getId();
        $storeId = $order->getStoreId();

        $isSandbox = $this->payplugConfig->getIsSandbox($storeId);

        $paymentData = [];
        $paymentData['notification_url'] = $this->urlBuilder->getUrl('payplug_payments/payment/ipn', [
            'ipn_store_id' => $storeId,
            'ipn_sandbox' => (int) $isSandbox,
        ]);
        $paymentData['hosted_payment'] = [
            'return_url' => $this->urlBuilder->getUrl('payplug_payments/payment/paymentReturn', [
                '_secure'  => true,
                'quote_id' => $quoteId,
            ]),
            'cancel_url' => $this->urlBuilder->getUrl('payplug_payments/payment/cancel', [
                '_secure'  => true,
                'quote_id' => $quoteId,
            ]),
        ];

        return $paymentData;
    }
}
