<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Helper\Transaction;

use Exception;
use Laminas\Uri\Http as UriHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\HTTP\Header;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Payplug\Payments\Api\Data\OrderPaymentInterface;
use Payplug\Payments\Helper\Card;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Helper\Country;
use Payplug\Payments\Helper\Phone;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\Order\Payment as PayplugOrderPayment;
use Payplug\Payments\Service\BuildHostedFieldsParamsHash;
use Payplug\Payments\Service\GetCartContextForTransaction;
use Payplug\Payments\Service\PlaceOrderExtraParamsRegistry;

class StandardBuilder extends AbstractBuilder
{
    /**
     * @param Card $cardHelper
     * @param Header $header
     * @param RemoteAddress $remoteAddress
     * @param BuildHostedFieldsParamsHash $buildHostedFieldsParamsHash
     * @param Context $context
     * @param Config $payplugConfig
     * @param Country $countryHelper
     * @param Phone $phoneHelper
     * @param Logger $logger
     * @param FormKey $formKey
     * @param UriHelper $uriHelper
     * @param PlaceOrderExtraParamsRegistry $placeOrderExtraParamsRegistry
     * @param GetCartContextForTransaction $getCartContextForTransaction
     */
    public function __construct(
        private readonly Card $cardHelper,
        private readonly Header $header,
        private readonly RemoteAddress $remoteAddress,
        private readonly BuildHostedFieldsParamsHash $buildHostedFieldsParamsHash,
        Context $context,
        Config $payplugConfig,
        Country $countryHelper,
        Phone $phoneHelper,
        Logger $logger,
        FormKey $formKey,
        UriHelper $uriHelper,
        PlaceOrderExtraParamsRegistry $placeOrderExtraParamsRegistry,
        GetCartContextForTransaction $getCartContextForTransaction
    ) {
        parent::__construct(
            $context,
            $payplugConfig,
            $countryHelper,
            $phoneHelper,
            $logger,
            $formKey,
            $uriHelper,
            $placeOrderExtraParamsRegistry,
            $getCartContextForTransaction
        );
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

    /**
     * Build PayPlug payment transaction
     *
     * @param OrderInterface|OrderAdapterInterface $order
     * @param InfoInterface $payment
     * @param CartInterface $quote
     *
     * @return array
     * @throws Exception
     */
    public function buildTransaction($order, InfoInterface $payment, $quote): array
    {
        $isHostedFieldsPayment = (bool) $payment->getAdditionalInformation(OrderPaymentInterface::HF_PAYMENT_KEY);

        if ($isHostedFieldsPayment === true) {
            return $this->buildHostedFieldsTransaction($order, $payment, $quote);
        }

        return parent::buildTransaction($order, $payment, $quote);
    }

    /**
     * Build PayPlug Hosted Fields payment transaction
     *
     * @param OrderAdapterInterface $order
     * @param InfoInterface $payment
     * @param CartInterface $quote
     * @return array
     * @throws LocalizedException
     * @throws Exception
     */
    private function buildHostedFieldsTransaction(
        OrderAdapterInterface $order,
        InfoInterface $payment,
        CartInterface $quote
    ): array {
        $hostedFieldsIdentifier = $this->payplugConfig->getHostedFieldsIdentifier();
        $hostedFieldsToken = (string) $payment->getAdditionalInformation(OrderPaymentInterface::HF_TOKEN_KEY);
        $hostedFieldsApiKeyId = $this->payplugConfig->getHostedFieldsApiKeyId();
        $hostedFieldsSelectedBrand = (string) $payment->getAdditionalInformation(OrderPaymentInterface::HF_BRAND_KEY);

        if ($hostedFieldsIdentifier === null
            || $hostedFieldsToken === ''
            || $hostedFieldsApiKeyId === null
            || $hostedFieldsSelectedBrand === ''
        ) {
            throw new Exception('Hosted Fields credentials are missing or token/brand are not set');
        }

        $billingAddress = $order->getBillingAddress();

        if ($billingAddress === null) {
            throw new Exception('Billing address is not set');
        }

        $customerFullname = $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname();
        $streetFull = implode(', ', array_filter([
            $billingAddress->getStreetLine1(),
            $billingAddress->getStreetLine2()
        ]));

        $quoteId = (int) ($quote->getId() ?: $this->placeOrderExtraParamsRegistry->getQuoteId());
        $storeId = (int) $order->getStoreId();

        $paymentMethod = $this->payplugConfig->isStandardPaymentModeDeferred() ? 'authorization' : 'payment';

        $payload = [
            'method' => $paymentMethod,
            'store_id' => $storeId, // for internal use
            'params' => [
                'IDENTIFIER' => $hostedFieldsIdentifier,
                'OPERATIONTYPE' => $paymentMethod,
                'AMOUNT' => (int) round($order->getGrandTotalAmount() * 100),
                'VERSION' => PayplugOrderPayment::HOSTED_FIELDS_PARAMS_VERSION,
                'SELECTEDBRAND' => $hostedFieldsSelectedBrand,
                'CLIENTIDENT' => $customerFullname,
                'CLIENTEMAIL' => $billingAddress->getEmail(),
                'CLIENTREFERRER' => $this->header->getHttpReferer(),
                'CLIENTUSERAGENT' => $this->header->getHttpUserAgent(),
                'CLIENTIP' => $this->remoteAddress->getRemoteAddress(),
                'ORDERID' => $order->getOrderIncrementId(),
                'DESCRIPTION' => 'Order #' . $order->getOrderIncrementId(),
                'APIKEYID' => $hostedFieldsApiKeyId,
                'HFTOKEN' => $hostedFieldsToken,
                '3DSECUREDISPLAYMODE' => 'raw',
                'REDIRECTURLSUCCESS' => $this->getReturnUrl($storeId, $quoteId),
                'REDIRECTURLCANCEL' => $this->getCancelUrl($storeId, $quoteId),
                'BILLINGADDRESS' => $streetFull,
                'BILLINGPOSTALCODE' => $billingAddress->getPostcode(),
                'BILLINGCITY' => $billingAddress->getCity(),
                'BILLINGCOUNTRY' => $billingAddress->getCountryId(),
                'MOBILEPHONE' => $billingAddress->getTelephone()
            ],
        ];

        $anonymizedTransactionDetails = $this->getAnonymizedTransactionDetails($order, $payload);
        $this->logger->info('New transaction (Hosted Fields)', ['details' => $anonymizedTransactionDetails]);

        $payload['params']['HASH'] = $this->buildHostedFieldsParamsHash->execute(
            $payload['params'],
            BuildHostedFieldsParamsHash::SEPARATOR_API_KEY
        );

        return $payload;
    }
}
