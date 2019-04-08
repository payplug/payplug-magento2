<?php

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
use Payplug\Payments\Model\OrderPaymentRepository;

class FetchTransactionInformationHandler implements HandlerInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var Logger
     */
    private $payplugLogger;

    /**
     * @var CardFactory
     */
    private $cardFactory;

    /**
     * @var CustomerCardRepository
     */
    private $customerCardRepository;

    /**
     * @var OrderSender
     */
    private $orderSender;

    /**
     * @var Card
     */
    private $cardHelper;

    /**
     * @var Config
     */
    private $payplugConfig;

    /**
     * @var OrderPaymentRepository
     */
    private $orderPaymentRepository;

    /**
     * TransactionIdHandler constructor.
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        SubjectReader $subjectReader,
        Logger $payplugLogger,
        CardFactory $cardFactory,
        CustomerCardRepository $customerCardRepository,
        OrderSender $orderSender,
        Card $cardHelper,
        Config $payplugConfig,
        OrderPaymentRepository $orderPaymentRepository
    ) {
        $this->subjectReader = $subjectReader;
        $this->payplugLogger = $payplugLogger;
        $this->cardFactory = $cardFactory;
        $this->customerCardRepository = $customerCardRepository;
        $this->orderSender = $orderSender;
        $this->cardHelper = $cardHelper;
        $this->payplugConfig = $payplugConfig;
        $this->orderPaymentRepository = $orderPaymentRepository;
    }

    /**
     * Handles response
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response)
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
                    $this->payplugLogger->critical($e);
                }

                $this->saveCustomerCard($payplugPayment, $order->getCustomerId(), $order->getStoreId());
            }
        }
    }

    /**
     * Save customer card
     *
     * @param \Payplug\Resource\Payment $payment
     * @param int|null                  $customerId
     * @param int                       $storeId
     */
    public function saveCustomerCard($payment, $customerId, $storeId)
    {
        if ($customerId == null) {
            return;
        }

        if ($payment->save_card == 1 || ($payment->card->id != '' && $payment->hosted_payment != '')) {
            try {
                $this->customerCardRepository->get($payment->card->id, 'card_token');
                return;
            } catch (NoSuchEntityException $e) {
                // Nothing to do, we want to create card if it does not already exist
            }

            /** @var \Payplug\Payments\Model\Customer\Card $card */
            $card = $this->cardFactory->create();
            $customerCardId = $this->cardHelper->getLastCardIdByCustomer($customerId) + 1;
            $companyId = (int) $this->payplugConfig->getConfigValue(
                'company_id',
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            $cardDate = $payment->card->exp_year . '-' . $payment->card->exp_month;
            $expDate = date('Y-m-t 23:59:59', strtotime($cardDate));
            $brand = $payment->card->brand;
            if (!in_array(strtolower($payment->card->brand), ['visa', 'mastercard', 'maestro', 'carte_bancaire'])) {
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
