<?php

namespace Payplug\Payments\Helper\Transaction;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\PaymentException;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Card;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Helper\Country;
use Payplug\Payments\Helper\Phone;
use Payplug\Payments\Logger\Logger;

class StandardBuilder extends AbstractBuilder
{
    /**
     * @var Card
     */
    private $cardHelper;

    /**
     * @param Context      $context
     * @param Config       $payplugConfig
     * @param Country      $countryHelper
     * @param Phone        $phoneHelper
     * @param Logger       $logger
     * @param Card         $cardHelper
     */
    public function __construct(
        Context $context,
        Config $payplugConfig,
        Country $countryHelper,
        Phone $phoneHelper,
        Logger $logger,
        Card $cardHelper
    ) {
        parent::__construct($context, $payplugConfig, $countryHelper, $phoneHelper, $logger);

        $this->cardHelper = $cardHelper;
    }

    /**
     * @inheritdoc
     */
    public function buildPaymentData($order, $payment, $quote)
    {
        $paymentData = parent::buildPaymentData($order, $payment, $quote);

        $storeId = $order->getStoreId();
        $customerCardId = $payment->getAdditionalInformation('payplug_payments_customer_card_id');
        $payment->unsAdditionalInformation('payplug_payments_customer_card_id');

        $currentCard = $this->getCustomerCardToken($customerCardId, $order->getCustomerId());
        $paymentData['allow_save_card'] = $this->canSaveCard($storeId, $currentCard, $order->getCustomerId());

        if ($this->isOneClick($storeId) && $currentCard != null) {
            $paymentData['payment_method'] = $currentCard;
            $paymentData['initiator'] = 'PAYER';
            unset($paymentData['hosted_payment']);
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
        if (empty($customerCardId)) {
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
