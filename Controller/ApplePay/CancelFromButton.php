<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Controller\ApplePay;

use Exception;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory as JsonResultFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Sales\Model\Order;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Service\GetCurrentOrder;

class CancelFromButton implements HttpGetActionInterface
{
    /**
     * @param Validator $formKeyValidator
     * @param RequestInterface $request
     * @param GetCurrentOrder $getCurrentOrder
     * @param Data $payplugHelper
     * @param Logger $logger
     * @param JsonResultFactory $resultJsonFactory
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        private readonly Validator $formKeyValidator,
        private readonly RequestInterface $request,
        private readonly GetCurrentOrder $getCurrentOrder,
        protected Data $payplugHelper,
        protected Logger $logger,
        private readonly JsonResultFactory $resultJsonFactory,
        private readonly CheckoutSession $checkoutSession
    ) {
    }

    /**
     * Cancel
     *
     * @return Json
     */
    public function execute(): Json
    {
        $formKeyValidation = $this->formKeyValidator->validate($this->request);
        $jsonResult = $this->resultJsonFactory->create();

        if ($formKeyValidation === false) {
            return $jsonResult->setData(
                [
                    'error' => true,
                    'message' => __('Your session has expired')
                ]
            );
        }

        try {
            $order = $this->getCurrentOrder->execute();

            if ($order instanceof Order) {
                $this->payplugHelper->cancelOrderAndInvoice($order);

                $this->checkoutSession->setLastQuoteId(null);
                $this->checkoutSession->setLastSuccessQuoteId(null);
                $this->checkoutSession->setLastOrderId(null);
                $this->checkoutSession->setLastRealOrderId(null);
                $this->checkoutSession->setLastOrderStatus(null);
            }

            return $jsonResult->setData(
                [
                    'error' => false,
                    'message' => __('The transaction was aborted and your card has not been charged')
                ]
            );
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());

            return $jsonResult->setData(
                [
                    'error' => true,
                    'message' => __('An error has occurred while cancelling the order')
                ]
            );
        }
    }
}
