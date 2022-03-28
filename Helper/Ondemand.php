<?php

namespace Payplug\Payments\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\PaymentException;
use Magento\Payment\Gateway\Data\Order\OrderAdapterFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Payplug\Payments\Helper\Http\OndemandClient;
use Payplug\Payments\Helper\Transaction\OndemandBuilder;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\OrderPaymentRepository;

class Ondemand extends AbstractHelper
{
    public const DESCRIPTION_MAX_LENGTH = 80;

    /**
     * @var \Payplug\Payments\Model\Order\PaymentFactory
     */
    private $payplugPaymentFactory;

    /**
     * @var OrderPaymentRepository
     */
    private $orderPaymentRepository;

    /**
     * @var OndemandBuilder
     */
    private $ondemandBuilder;

    /**
     * @var OrderAdapterFactory
     */
    private $orderAdapterFactory;

    /**
     * @var Logger
     */
    private $payplugLogger;

    /**
     * @var OndemandClient
     */
    private $ondemandClient;

    /**
     * @param Context                                      $context
     * @param \Payplug\Payments\Model\Order\PaymentFactory $payplugPaymentFactory
     * @param OrderPaymentRepository                       $orderPaymentRepository
     * @param OndemandBuilder                              $ondemandBuilder
     * @param OrderAdapterFactory                          $orderAdapterFactory
     * @param Logger                                       $payplugLogger
     * @param OndemandClient                               $ondemandClient
     */
    public function __construct(
        Context $context,
        \Payplug\Payments\Model\Order\PaymentFactory $payplugPaymentFactory,
        OrderPaymentRepository $orderPaymentRepository,
        OndemandBuilder $ondemandBuilder,
        OrderAdapterFactory $orderAdapterFactory,
        Logger $payplugLogger,
        OndemandClient $ondemandClient
    ) {
        parent::__construct($context);

        $this->payplugPaymentFactory = $payplugPaymentFactory;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->ondemandBuilder = $ondemandBuilder;
        $this->orderAdapterFactory = $orderAdapterFactory;
        $this->payplugLogger = $payplugLogger;
        $this->ondemandClient = $ondemandClient;
    }

    /**
     * Send OnDemand payment link
     *
     * @param Order                                 $order
     * @param \Payplug\Payments\Model\Order\Payment $lastOrderPayment
     * @param array                                 $paymentLinkData
     *
     * @throws PaymentException
     */
    public function sendNewPaymentLink($order, $lastOrderPayment, $paymentLinkData)
    {
        if ($lastOrderPayment === null) {
            throw new PaymentException(__('Unable to find payment linked to order %1', $order->getIncrementId()));
        }

        $payplugPayment = $lastOrderPayment->retrieve($order->getStoreId());
        if ($payplugPayment->is_paid) {
            $exceptionMessage = 'Last payment %1 has already been paid. ' .
                'Please wait for the automatic notification or update the payment manually.';
            throw new PaymentException(__($exceptionMessage, $lastOrderPayment->getPaymentId()));
        }

        $orderAdapter = $this->orderAdapterFactory->create(
            ['order' => $order]
        );

        /** @var Payment $payment */
        $payment = $order->getPayment();
        foreach ($paymentLinkData as $key => $value) {
            $payment->setAdditionalInformation($key, $value);
        }

        $quote = new \Magento\Framework\DataObject();
        $quote->setId($order->getQuoteId());

        $billingAddress = new \Magento\Framework\DataObject();
        $billingAddress->setCustomerAddressId($order->getBillingAddress()->getCustomerAddressId());
        $quote->setBillingAddress($billingAddress);

        if ($order->getShippingAddress() !== null) {
            $shippingAddress = new \Magento\Framework\DataObject();
            $shippingAddress->setCustomerAddressId($order->getShippingAddress()->getCustomerAddressId());
            $quote->setShippingAddress($shippingAddress);
        }

        $newTransaction = $this->ondemandBuilder->buildTransaction($orderAdapter, $payment, $quote);

        $payplugPayment = $lastOrderPayment->abort($order->getStoreId());
        if ($payplugPayment === null) {
            throw new PaymentException(__('Unable to abort payment %1', $lastOrderPayment->getPaymentId()));
        }

        $result = $this->ondemandClient->placeRequest($newTransaction);
        $this->saveTransactionInfo($payment, $result);

        $newPaymentId = $result['payment']->id;
        $order->addCommentToStatusHistory(__('New payment reference %1', $newPaymentId));

        $payment->setLastTransId($newPaymentId);
        foreach ($order->getInvoiceCollection() as $invoice) {
            $invoice->setTransactionId($newPaymentId)->save();
        }
    }

    /**
     * Save OnDemand data on order payment
     *
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param array                              $transactionData
     */
    public function saveTransactionInfo($payment, $transactionData)
    {
        $payplugPayment = $transactionData['payment'];
        $ondemandData = $transactionData['ondemandData'];

        $payment->setTransactionId($payplugPayment->id);
        $payment->setIsTransactionPending(true);
        $payment->setIsTransactionClosed(false);
        $payment->setShouldCloseParentTransaction(false);

        /** @var \Payplug\Payments\Model\Order\Payment $orderPayment */
        $orderPayment = $this->payplugPaymentFactory->create();
        $orderPayment->setOrderId($payment->getOrder()->getIncrementId());
        $orderPayment->setPaymentId($payplugPayment->id);
        $orderPayment->setIsSandbox(!$payplugPayment->is_live);
        $orderPayment->setSentBy($ondemandData['sent_by']);
        $orderPayment->setSentByValue($ondemandData['sent_by_value']);
        $orderPayment->setLanguage($ondemandData['language']);
        $orderPayment->setDescription($ondemandData['description']);
        $this->orderPaymentRepository->save($orderPayment);
    }
}
