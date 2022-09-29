<?php

namespace Payplug\Payments\Controller\ApplePay;

use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\PaymentException;
use Magento\Sales\Model\Order;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Controller\Payment\AbstractPayment;

class GetTransactionData extends AbstractPayment
{
    /**
     * Retrieve PayPlug Apple Pay transaction data
     *
     * @return Json
     */
    public function execute()
    {
        /** @var Json $response */
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $responseParams = [
            'merchand_data' => [],
            'error' => true,
            'message' => __('An error occurred while processing the order.'),
        ];

        try {
            $order = $this->getLastOrder();
            $merchandSession = $order->getPayment()->getAdditionalInformation('merchand_session');
            $order->getPayment()->unsAdditionalInformation('merchand_session');

            if (empty($merchandSession)) {
                throw new \Exception('Could not retrieve merchand session');
            }

            $response->setData([
                'merchand_data' => $merchandSession,
                'error' => false,
            ]);

            return $response;
        } catch (PayplugException $e) {
            $this->logger->error('Could not retrieve apple pay transaction data', [
                'message' => $e->__toString(),
                'exception' => $e,
            ]);
            $response->setData($responseParams);

            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Could not retrieve apple pay transaction data', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            $response->setData($responseParams);

            return $response;
        }
    }

    /**
     * Get last order
     *
     * @return Order
     *
     * @throws \Exception
     */
    private function getLastOrder()
    {
        $lastIncrementId = $this->getCheckout()->getLastRealOrderId();

        if (!$lastIncrementId) {
            throw new \Exception('Could not retrieve last order id');
        }
        $order = $this->salesOrderFactory->create();
        $order->loadByIncrementId($lastIncrementId);

        if (!$order->getId()) {
            throw new \Exception(sprintf('Could not retrieve order with id %s', $lastIncrementId));
        }

        return $order;
    }
}
