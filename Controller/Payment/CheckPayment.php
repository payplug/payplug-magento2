<?php


declare(strict_types=1);

namespace Payplug\Payments\Controller\Payment;

use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Payplug\Payments\Exception\OrderAlreadyProcessingException;

class CheckPayment extends AbstractPayment
{
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

        $paymentId = $this->getRequest()->getParam("payment_id");
        $order = $this->getLastOrder();
        $storeId = $order->getStoreId();

        if (empty($paymentId)) {
            throw new \Exception('Could not retrieve payment id for integrated payment');
        }

        $payment = $this->payplugHelper->getPayment($order, $storeId);
        if ((isset($payment->failure)) && (!empty($payment->failure)) || ($payment->is_paid === false && is_null($payment->paid_at))) {
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

    /**
     * @return Order
     *
     * @throws \Exception
     */
    private function getLastOrder(): Order
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
