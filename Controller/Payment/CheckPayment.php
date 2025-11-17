<?php


declare(strict_types=1);

namespace Payplug\Payments\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Payplug\Payments\Exception\OrderAlreadyProcessingException;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Service\GetCurrentOrder;

class CheckPayment extends AbstractPayment
{
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderFactory $salesOrderFactory,
        Logger $logger,
        Data $payplugHelper,
        protected GetCurrentOrder $getCurrentOrder
    ) {
        parent::__construct($context, $checkoutSession, $salesOrderFactory, $logger, $payplugHelper);
    }

    /**
     * Retrieve PayPlug Standard payment url
     *
     * @return Json
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws OrderAlreadyProcessingException
     */
    public function execute(): Json
    {
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        $paymentId = $this->getRequest()->getParam('payment_id');
        $order = $this->getCurrentOrder->execute();

        if (empty($paymentId)) {
            throw new \Exception('Could not retrieve payment id for integrated payment');
        }

        $payment = $this->payplugHelper->getPayment($order);
        $isAuthorizedDeferred = (!empty($payment->authorization) && (int)$payment->authorization->authorized_amount > 0 && (int)$payment->authorization->authorized_at > 0);

        if ((!empty($payment->failure) || (!$payment->is_paid && is_null($payment->paid_at)))
            && !$isAuthorizedDeferred) {
            $order->setStatus(Order::STATE_CANCELED);
            $this->payplugHelper->updateOrder($order, ['status' => Order::STATE_CANCELED]);
            $data = [
                "error" => true,
                'message' => __('The transaction was aborted and your card has not been charged'),
            ];
            $response->setData($data);

            return $response;
        }
        $data = ['error' => false];
        $response->setData($data);

        return $response;
    }
}
