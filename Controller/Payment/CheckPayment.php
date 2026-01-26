<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Controller\Payment;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Service\GetCurrentOrder;

class CheckPayment extends AbstractPayment
{
    /**
     * @param GetCurrentOrder $getCurrentOrder
     * @param Context $context
     * @param Session $checkoutSession
     * @param OrderFactory $salesOrderFactory
     * @param Logger $logger
     * @param Data $payplugHelper
     */
    public function __construct(
        private readonly GetCurrentOrder $getCurrentOrder,
        Context $context,
        Session $checkoutSession,
        OrderFactory $salesOrderFactory,
        Logger $logger,
        Data $payplugHelper
    ) {
        parent::__construct($context, $checkoutSession, $salesOrderFactory, $logger, $payplugHelper);
    }

    /**
     * Retrieve PayPlug Standard payment url
     *
     * @return Json
     * @throws LocalizedException
     * @throws Exception
     */
    public function execute(): Json
    {
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        $paymentId = $this->getRequest()->getParam('payment_id');
        $order = $this->getCurrentOrder->execute();

        if (empty($paymentId)) {
            throw new LocalizedException(__('Could not retrieve payment id for integrated payment'));
        }

        $payment = $this->payplugHelper->getPayment($order);
        $isAuthorizedDeferred = (
            !empty($payment->authorization)
            && (int)$payment->authorization->authorized_amount > 0
            && (int)$payment->authorization->authorized_at > 0
        );

        if ((!empty($payment->failure) || (!$payment->is_paid && $payment->paid_at === null))
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
