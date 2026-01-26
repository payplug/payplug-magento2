<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Gateway\Response\Standard;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Card;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\Customer\CardFactory;
use Payplug\Payments\Model\CustomerCardRepository;
use Payplug\Resource\Payment as PaymentResource;

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
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
        private readonly Logger $payplugLogger,
        private readonly CardFactory $cardFactory,
        private readonly CustomerCardRepository $customerCardRepository,
        private readonly OrderSender $orderSender,
        private readonly Card $cardHelper,
        private readonly Config $payplugConfig
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

                $this->saveCustomerCard($payplugPayment, $order->getCustomerId(), $order->getStoreId());
            }
        }
    }

    /**
     * Save customer card
     *
     * @param PaymentResource $payment
     * @param int|null $customerId
     * @param int $storeId
     */
    public function saveCustomerCard($payment, $customerId, $storeId)
    {
        if ($customerId == null) {
            return;
        }

        if ($payment->card && $payment->card->id != '') {
            try {
                $this->customerCardRepository->get($payment->card->id, 'card_token');
                return;
            } catch (NoSuchEntityException) {
                $this->payplugLogger->info('Nothing to do, we want to create card if it does not already exist');
            }

            /** @var \Payplug\Payments\Model\Customer\Card $card */
            $card = $this->cardFactory->create();
            $customerCardId = $this->cardHelper->getLastCardIdByCustomer($customerId) + 1;
            $companyId = (int) $this->payplugConfig->getConfigValue(
                'company_id',
                ScopeInterface::SCOPE_STORE,
                (int)$storeId
            );
            $cardDate = $payment->card->exp_year . '-' . $payment->card->exp_month;
            $expDate = date('Y-m-t 23:59:59', strtotime($cardDate));
            $brand = $payment->card->brand;
            if (!in_array(strtolower($brand ?? ''), ['visa', 'mastercard', 'maestro', 'carte_bancaire'])) {
                $brand = 'other';
            }

            $card->setCustomerId($customerId);
            $card->setCustomerCardId($customerCardId);
            $card->setCompanyId($companyId);
            $card->setIsSandbox(!$payment->is_live);
            $card->setCardToken($payment->card->id);
            $card->setLastFour($payment->card->last4);
            $card->setExpDate($expDate);
            $card->setBrand($brand);
            $card->setCountry($payment->card->country);
            $card->setMetadata($payment->card->metadata);

            $this->customerCardRepository->save($card);
        }
    }
}
