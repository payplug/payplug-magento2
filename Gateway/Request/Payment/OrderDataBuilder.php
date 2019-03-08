<?php

namespace Payplug\Payments\Gateway\Request\Payment;

use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Payplug\Payments\Gateway\Helper\SubjectReader;
use Payplug\Payments\Helper\Config;

class OrderDataBuilder implements BuilderInterface
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
    public function __construct(SubjectReader $subjectReader, UrlInterface $urlBuilder, Config $payplugConfig)
    {
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

        $currency = $order->getCurrencyCode();
        $quoteId = $this->subjectReader->getQuote()->getId();

        if ($currency === null) {
            $currency = 'EUR';
        }

        $metadata = [
            'ID Quote' => $quoteId,
            'Order'    => $order->getOrderIncrementId(),
            'Website'  => $this->urlBuilder->getUrl(''),
        ];

        $paymentTab = [
            'currency' => $currency,
            'metadata' => $metadata,
            'store_id' => $order->getStoreId(),
        ];

        return $paymentTab;
    }
}
