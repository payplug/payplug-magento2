<?php

namespace Payplug\Payments\Gateway\Response\InstallmentPlan;

use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;
use Payplug\Payments\Gateway\Helper\SubjectReader;
use Payplug\Payments\Model\Order\InstallmentPlanFactory as PayplugInstallmentPlanFactory;
use Payplug\Payments\Model\OrderInstallmentPlanRepository;

class PaymentHandler implements HandlerInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var PayplugInstallmentPlanFactory
     */
    private $installmentPlanFactory;

    /**
     * @var OrderInstallmentPlanRepository
     */
    private $installmentPlanRepository;

    /**
     * TransactionIdHandler constructor.
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        SubjectReader $subjectReader,
        PayplugInstallmentPlanFactory $installmentPlanFactory,
        OrderInstallmentPlanRepository $installmentPlanRepository
    ) {
        $this->subjectReader = $subjectReader;
        $this->installmentPlanFactory = $installmentPlanFactory;
        $this->installmentPlanRepository = $installmentPlanRepository;
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
            $installmentPlan = $response['installment_plan'];

            /** @var Payment $payment */
            $payment = $paymentDO->getPayment();

            $payment->setTransactionId($installmentPlan->id);
            $payment->setAdditionalInformation('payment_url', $installmentPlan->hosted_payment->payment_url);
            $payment->setIsTransactionPending(true);
            $payment->setIsTransactionClosed(false);
            $payment->setShouldCloseParentTransaction(false);

            /** @var \Payplug\Payments\Model\Order\InstallmentPlan $orderInstallmentPlan */
            $orderInstallmentPlan = $this->installmentPlanFactory->create();
            $orderInstallmentPlan->setOrderId($payment->getOrder()->getIncrementId());
            $orderInstallmentPlan->setInstallmentPlanId($installmentPlan->id);
            $orderInstallmentPlan->setIsSandbox(!$installmentPlan->is_live);
            $orderInstallmentPlan->setStatus(\Payplug\Payments\Model\Order\InstallmentPlan::STATUS_NEW);
            $this->installmentPlanRepository->save($orderInstallmentPlan);
        }
    }
}
