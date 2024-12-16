<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\ApplePay;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\OrderFactory;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Controller\Payment\AbstractPayment;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Service\GetCurrentOrderIncrementId;

class UpdateTransaction extends AbstractPayment
{
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderFactory $salesOrderFactory,
        Logger $logger,
        Data $payplugHelper,
        protected GetCurrentOrderIncrementId $currentOrderIncrementId
    ) {
        parent::__construct($context, $checkoutSession, $salesOrderFactory, $logger, $payplugHelper);
    }

    /**
     * Update PayPlug Apple Pay transaction data
     */
    public function execute(): Json
    {
        /** @var Json $response */
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $response->setData([
            'error' => true,
            'message' => (string)__('An error occurred while processing the order.'),
        ]);

        try {
            $order = $this->currentOrderIncrementId->getLastRealOrder();
            if (!$order) {
                throw new \Exception('Could not retrieve last order in UpdateTransaction');
            }
            $token = $this->getRequest()->getParam('token');
            if (empty($token)) {
                throw new \Exception('Could not retrieve token');
            }

            $payplugPayment = $this->payplugHelper->getOrderPayment($order->getIncrementId());
            $updatedPayment = $payplugPayment->update([
                'apple_pay' => [
                    'payment_token' => $token,
                ],
            ]);

            if ($updatedPayment->is_paid) {
                $response->setData([
                    'error' => false,
                ]);
            }

            return $response;
        } catch (PayplugException $e) {
            $this->logger->error('Could not update apple pay transaction', [
                'message' => $e->__toString(),
                'exception' => $e,
            ]);

            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Could not update apple pay transaction', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return $response;
        }
    }
}
