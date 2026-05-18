<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Gateway\Response\Standard;

use DateInterval;
use DateTime;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenManagement;
use Payplug\Payments\Api\Data\PaymentTokenInterface;
use Payplug\Payments\Gateway\Config\Standard;
use Payplug\Payments\Helper\Card;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\Customer\CardFactory;
use Payplug\Payments\Model\CustomerCardRepository;
use Payplug\Resource\Payment as PaymentResource;
use Throwable;

class FetchTransactionInformationHandler implements HandlerInterface
{
    /**
     * @param SubjectReader $subjectReader
     * @param Logger $payplugLogger
     * @param CardFactory $cardFactory
     * @param CustomerCardRepository $customerCardRepository
     * @param OrderSender $orderSender
     * @param Card $cardHelper
     * @param Config $payplugConfig
     * @param PaymentTokenManagement $paymentTokenManagement
     * @param PaymentTokenFactoryInterface $paymentTokenFactory
     * @param StoreManagerInterface $storeManager
     * @param SerializerInterface $serializer
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
        private readonly Logger $payplugLogger,
        private readonly CardFactory $cardFactory,
        private readonly CustomerCardRepository $customerCardRepository,
        private readonly OrderSender $orderSender,
        private readonly Card $cardHelper,
        private readonly Config $payplugConfig,
        private readonly PaymentTokenManagement $paymentTokenManagement,
        private readonly PaymentTokenFactoryInterface $paymentTokenFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly SerializerInterface $serializer,
        private readonly PaymentTokenRepositoryInterface $paymentTokenRepository,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    /**
     * Handles response
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = $this->subjectReader->readPayment($handlingSubject);

        if ($paymentDO->getPayment() instanceof Payment) {
            /** @var Payment $payment */
            $payment = $paymentDO->getPayment();
            $order = $payment->getOrder();

            $payplugPayment = $response['payment'];

            if ($payplugPayment->failure) {
                $payment->setIsTransactionDenied(true);
            } elseif ($payplugPayment->is_paid) {
                $payment->setIsTransactionApproved(true);
                try {
                    $this->orderSender->send($order);
                } catch (\Exception $e) {
                    $this->payplugLogger->critical($e->getMessage());
                }

                $customerId = $order->getCustomerId();

                if ($customerId !== null) {
                    $this->saveCustomerCard($payplugPayment, (int) $customerId, (int) $order->getStoreId());
                }

            }
        }
    }

    /**
     * Save customer card
     *
     * @param PaymentResource $payment
     * @param int $customerId
     * @param int $storeId
     */
    public function saveCustomerCard(PaymentResource $payment, int $customerId, int $storeId): void
    {
        if (empty($payment->card->id)) {
            return;
        }

        $isHostedFieldsPayment = (bool) $payment->is_hosted_fields ?? false;

        if ($isHostedFieldsPayment === true) {
            $this->saveHostedFieldsCustomerCard($payment, $customerId, $storeId);
        } else {
            $this->savePayplugRetailCustomerCard($payment, $customerId, $storeId);
        }
    }

    /**
     * Save Hosted Fields customer card
     *
     * @param PaymentResource $paymentResource
     * @param int $customerId
     * @param int $storeId
     * @return void
     */
    private function saveHostedFieldsCustomerCard(
        PaymentResource $paymentResource,
        int $customerId,
        int $storeId
    ): void {
        $this->payplugLogger->info('Saving HF customer card token');

        $hfIdentifier = $this->payplugConfig->getHostedFieldsIdentifier();
        $cardId = $paymentResource->card->id ?? null;
        $expMonth = $paymentResource->card->exp_month ?? null;
        $expYear = $paymentResource->card->exp_year ?? null;

        if ($hfIdentifier === null || $cardId === null || $expMonth === null || $expYear === null) {
            return;
        }

        $tokenDetails = [
            PaymentTokenInterface::DETAIL_BRAND => strtolower($paymentResource->card->brand),
            PaymentTokenInterface::MASKED_CC => $paymentResource->card->last4,
            PaymentTokenInterface::EXP_DATE => $expMonth . '/' . $expYear,
            PaymentTokenInterface::HOSTED_FIELD_IDENTIFIER => $hfIdentifier,
        ];
        $serializedTokenDetails = $this->serializer->serialize($tokenDetails);
        $publicHash = $this->encryptor->getHash(
            $customerId
            . Standard::METHOD_CODE
            . PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD
            . $serializedTokenDetails
        );

        try {
            $websiteId = (int) $this->storeManager->getStore($storeId)->getWebsiteId();
        } catch (NoSuchEntityException) {
            $this->payplugLogger->error('Website for store ' . $storeId . ' not found, skipping');
            return;
        }

        try {
            $expiryDateTime = new DateTime(sprintf('%04d-%02d-01 00:00:00', 2000 + $expYear, $expMonth));
        } catch (Throwable) {
            $this->payplugLogger->error('Invalid card expiry date');
            return;
        }

        $expiryDateTime->add(new DateInterval('P1M'));

        /**
         * Need to set a max date for timestamp type column to avoid MySQL error.
         * Waiting for column update by Adobe to fix this.
         */
        $maxDateTime = new DateTime('2038-01-19 00:00:00');

        if ($expiryDateTime > $maxDateTime) {
            $expiryDateTime = $maxDateTime;
        }

        $formatedExpiresAt = $expiryDateTime->format('Y-m-d 00:00:00');

        $paymentToken = $this->paymentTokenManagement->getByPublicHash($publicHash, $customerId);

        if ($paymentToken === null) {
            $paymentToken = $this->paymentTokenFactory->create();
        }

        $paymentToken->setCustomerId($customerId);
        $paymentToken->setType(PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD);
        $paymentToken->setGatewayToken($cardId);
        $paymentToken->setIsActive(true);
        $paymentToken->setIsVisible(true);
        $paymentToken->setPaymentMethodCode(Standard::METHOD_CODE);
        $paymentToken->setWebsiteId($websiteId);
        $paymentToken->setExpiresAt($formatedExpiresAt);
        $paymentToken->setTokenDetails($serializedTokenDetails);
        $paymentToken->setPublicHash($publicHash);

        $this->paymentTokenRepository->save($paymentToken);
    }

    /**
     * Save Payplug Retail customer card
     *
     * @param PaymentResource $paymentResource
     * @param int $customerId
     * @param int $storeId
     * @return void
     */
    private function savePayplugRetailCustomerCard(
        PaymentResource $paymentResource,
        int $customerId,
        int $storeId
    ): void {
        try {
            $this->customerCardRepository->get($paymentResource->card->id, 'card_token');
            return;
        } catch (NoSuchEntityException) {
            $this->payplugLogger->info('Nothing to do, we want to create card if it does not already exist');
        }

        $card = $this->cardFactory->create();
        $customerCardId = $this->cardHelper->getLastCardIdByCustomer($customerId) + 1;
        $companyId = (int) $this->payplugConfig->getConfigValue(
            'company_id',
            ScopeInterface::SCOPE_STORE,
            (int)$storeId
        );
        $cardDate = $paymentResource->card->exp_year . '-' . $paymentResource->card->exp_month;
        $expDate = date('Y-m-t 23:59:59', strtotime($cardDate));
        $brand = $paymentResource->card->brand;
        if (!in_array(strtolower($brand ?? ''), ['visa', 'mastercard', 'maestro', 'carte_bancaire'])) {
            $brand = 'other';
        }

        $card->setCustomerId($customerId);
        $card->setCustomerCardId($customerCardId);
        $card->setCompanyId($companyId);
        $card->setIsSandbox(!$paymentResource->is_live);
        $card->setCardToken($paymentResource->card->id);
        $card->setLastFour($paymentResource->card->last4);
        $card->setExpDate($expDate);
        $card->setBrand($brand);
        $card->setCountry($paymentResource->card->country);
        $card->setMetadata($paymentResource->card->metadata);

        $this->customerCardRepository->save($card);
    }
}
