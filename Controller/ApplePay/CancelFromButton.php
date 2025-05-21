<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\ApplePay;

use Exception;
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
    public function __construct(
        private readonly Validator $formKeyValidator,
        private readonly RequestInterface $request,
        private readonly GetCurrentOrder $getCurrentOrder,
        protected Data $payplugHelper,
        protected Logger $logger,
        private readonly JsonResultFactory $resultJsonFactory
    ) {
    }

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
            /** @var Order $order */
            $order = $this->getCurrentOrder->execute();
            $this->payplugHelper->cancelOrderAndInvoice($order);

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
