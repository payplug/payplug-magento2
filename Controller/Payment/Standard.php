<?php

namespace Payplug\Payments\Controller\Payment;

use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\PaymentException;
use Magento\Sales\Model\Order;
use Payplug\Exception\PayplugException;

class Standard extends AbstractPayment
{
    public function execute()
    {
        $shouldRedirect = $this->getRequest()->getParam('should_redirect', true);

        /** @var Json $response */
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $responseParams = [
            'url' => $this->_url->getUrl('payplug_payments/payment/cancel', ['is_canceled_by_provider' => true]),
            'error' => true,
            'message' => __('An error occurred while processing the order.')
        ];

        try {
            $order = $this->getLastOrder();
            $url = $order->getPayment()->getAdditionalInformation('payment_url');
            $order->getPayment()->unsAdditionalInformation('payment_url');

            if (empty($url)) {
                throw new \Exception('Could not retrieve payment url');
            }

            if ($shouldRedirect) {
                return $this->resultRedirectFactory->create()->setUrl($url);
            }

            $response->setData([
                'url' => $url,
                'error' => false,
            ]);

            return $response;
        } catch (PayplugException $e) {
            $this->logger->error($e->__toString());
            if ($shouldRedirect) {
                $this->messageManager->addErrorMessage(
                    __('An error occured while processing your payment. Please try again.')
                );
                return $this->resultRedirectFactory->create()->setPath(
                    'payplug_payments/payment/cancel',
                    ['is_canceled_by_provider' => true]
                );
            }

            $response->setData($responseParams);

            return $response;
        } catch (PaymentException $e) {
            $this->logger->error($e->getMessage());
            if ($shouldRedirect) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $this->resultRedirectFactory->create()->setPath(
                    'payplug_payments/payment/cancel',
                    ['is_canceled_by_provider' => true]
                );
            }

            $response->setData($responseParams);

            return $response;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            if ($shouldRedirect) {
                $this->messageManager->addErrorMessage(
                    __('An error occured while processing your payment. Please try again.')
                );
                return $this->resultRedirectFactory->create()->setPath(
                    'payplug_payments/payment/cancel',
                    ['is_canceled_by_provider' => true]
                );
            }

            $response->setData($responseParams);

            return $response;
        }
    }

    /**
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
