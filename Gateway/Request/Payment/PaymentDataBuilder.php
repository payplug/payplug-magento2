<?php

namespace Payplug\Payments\Gateway\Request\Payment;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Gateway\Helper\SubjectReader;
use Payplug\Payments\Helper\Card;
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
     * @var Card
     */
    private $cardHelper;

    /**
     * Constructor
     *
     * @param SubjectReader $subjectReader
     * @param UrlInterface  $urlBuilder
     * @param Config        $payplugConfig
     * @param Card          $cardHelper
     */
    public function __construct(
        SubjectReader $subjectReader,
        UrlInterface $urlBuilder,
        Config $payplugConfig,
        Card $cardHelper
    ) {
        $this->subjectReader = $subjectReader;
        $this->urlBuilder = $urlBuilder;
        $this->payplugConfig = $payplugConfig;
        $this->cardHelper = $cardHelper;
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
            'ipn_sandbox'  => (int)$isSandbox,
        ]);
        $paymentData['force_3ds'] = false;

        $customerCardId = $paymentDO->getPayment()->getAdditionalInformation('payplug_payments_customer_card_id');
        $paymentDO->getPayment()->unsAdditionalInformation('payplug_payments_customer_card_id');

        $currentCard = $this->getCustomerCardToken($customerCardId, $order->getCustomerId());
        $paymentData['allow_save_card'] = $this->canSaveCard($storeId, $currentCard, $order->getCustomerId());

        if ($this->isOneClick($storeId) && $currentCard != null) {
            $paymentData['payment_method'] = $currentCard;
        } else {
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
        }

        return $paymentData;
    }

    /**
     * Get customer card token
     *
     * @param int|null $customerCardId
     * @param int|null $customerId
     *
     * @return string|null
     *
     * @throws PaymentException
     */
    private function getCustomerCardToken($customerCardId, $customerId)
    {
        if ($customerCardId === null) {
            return null;
        }

        if (empty($customerId)) {
            return null;
        }

        try {
            $currentCard = $this->cardHelper->getCustomerCard($customerId, $customerCardId);
        } catch (NoSuchEntityException $e) {
            throw new PaymentException(__('This card does not exist or has been deleted.'));
        }

        return $currentCard->getCardToken();
    }

    /**
     * Check if card can be saved on payment page
     *
     * @param string|null $currentCard
     * @param int|null    $customerId
     *
     * @return bool
     */
    private function canSaveCard($storeId, $currentCard, $customerId)
    {
        if (!$this->isOneClick($storeId)) {
            return false;
        }

        if ($currentCard !== null) {
            return false;
        }

        if (empty($customerId)) {
            return false;
        }

        return true;
    }

    /**
     * Check if PayPlug One-click payment is enabled
     *
     * @return bool
     */
    private function isOneClick($storeId)
    {
        return $this->payplugConfig->getConfigValue(
            'one_click',
            ScopeInterface::SCOPE_STORE,
            $storeId,
            'payment/payplug_payments_standard/'
        );
    }
}
