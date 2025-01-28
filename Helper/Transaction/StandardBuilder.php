<?php

declare(strict_types=1);

namespace Payplug\Payments\Helper\Transaction;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\PaymentException;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Payplug\Payments\Helper\Card;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Helper\Country;
use Payplug\Payments\Helper\Phone;
use Payplug\Payments\Logger\Logger;

class StandardBuilder extends AbstractBuilder
{
    public function __construct(
        Context $context,
        Config $payplugConfig,
        Country $countryHelper,
        Phone $phoneHelper,
        Logger $logger,
        FormKey $formKey,
        private Card $cardHelper
    ) {
        parent::__construct($context, $payplugConfig, $countryHelper, $phoneHelper, $logger, $formKey);
    }

    /**
     * @inheritdoc
     */
    public function buildPaymentData($order, InfoInterface $payment, CartInterface $quote): array
    {
        $paymentData = parent::buildPaymentData($order, $payment, $quote);

        $storeId = (int)$order->getStoreId();
        $customerCardId = (int)$payment->getAdditionalInformation('payplug_payments_customer_card_id');
        $payment->unsAdditionalInformation('payplug_payments_customer_card_id');

        $currentCard = $this->getCustomerCardToken($customerCardId, (int)$order->getCustomerId());
        $paymentData['allow_save_card'] = $this->canSaveCard($storeId, $currentCard, (int)$order->getCustomerId());

        if ($this->isOneClick($storeId) && $currentCard != null) {
            $paymentData['payment_method'] = $currentCard;
            $paymentData['initiator'] = 'PAYER';
        } elseif ($this->payplugConfig->isIntegrated()) {
            $paymentData['integration'] = 'INTEGRATED_PAYMENT';
            if (isset($paymentData['hosted_payment']['cancel_url'])) {
                unset($paymentData['hosted_payment']['cancel_url']);
            }
        }

        // Manage the deferred paiement mode
        if ($this->payplugConfig->isStandardPaymentModeDeferred()) {
            $paymentData['auto_capture'] = false;
        }

        return $paymentData;
    }

    /**
     * @inheritdoc
     */
    public function buildAmountData($order): array
    {
        $amountData = parent::buildAmountData($order);
        if ($this->payplugConfig->isStandardPaymentModeDeferred()) {
            $amountData['authorized_amount'] = $amountData['amount'];
            unset($amountData['amount']);
        }

        return $amountData;
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
    private function getCustomerCardToken(?int $customerCardId, ?int $customerId): ?string
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
     * @param int         $storeId
     * @param string|null $currentCard
     * @param int|null    $customerId
     *
     * @return bool
     */
    private function canSaveCard(int $storeId, ?string $currentCard, ?int $customerId): bool
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
     * @param int $storeId
     *
     * @return bool
     */
    private function isOneClick(int $storeId): bool
    {
        return $this->payplugConfig->isOneClick($storeId);
    }
}
