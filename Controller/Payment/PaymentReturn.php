<?php

namespace Payplug\Payments\Controller\Payment;

use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Exception\OrderAlreadyProcessingException;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;

class PaymentReturn extends AbstractPayment
{
    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session       $checkoutSession
     * @param \Magento\Sales\Model\OrderFactory     $salesOrderFactory
     * @param Logger                                $logger
     * @param Data                                  $payplugHelper
     * @param OrderRepository                       $orderRepository
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $salesOrderFactory,
        Logger $logger,
        Data $payplugHelper,
        OrderRepository $orderRepository
    ) {
        parent::__construct($context, $checkoutSession, $salesOrderFactory, $logger, $payplugHelper);
        $this->orderRepository = $orderRepository;
    }

    /**
     * Handle return from PayPlug payment page
     *
     * @return mixed
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        $redirectUrlSuccess = 'checkout/onepage/success';
        try {
            $lastIncrementId = $this->getCheckout()->getLastRealOrderId();

            if (!$lastIncrementId) {
                $this->logger->error('Could not retrieve last order id');

                return $resultRedirect->setPath($redirectUrlSuccess);
            }
            $order = $this->salesOrderFactory->create();
            $order->loadByIncrementId($lastIncrementId);

            if (!$order->getId()) {
                $this->logger->error(sprintf('Could not retrieve order with id %s', $lastIncrementId));

                return $resultRedirect->setPath($redirectUrlSuccess);
            }

            $this->payplugHelper->checkPaymentFailureAndAbortPayment($order);
            $order = $this->payplugHelper->updateOrder($order);

            if ($this->payplugHelper->isOrderValidated($order)) {
                return $resultRedirect->setPath($redirectUrlSuccess);
            } else {
                $failureMessage = null;
                if ($this->payplugHelper->isPaymentBancontact($order) || $this->payplugHelper->isPaymentAmex($order)) {
                    $failureMessage = 'The transaction was aborted and your card has not been charged';
                }

                return $this->resultFactory
                    ->create(ResultFactory::TYPE_FORWARD)
                    ->setParams([
                        'is_canceled_by_provider' => true,
                        'failure_message' => $failureMessage,
                    ])
                    ->forward('cancel');
            }
        } catch (PayplugException $e) {
            $this->logger->error($e->__toString());

            return $resultRedirect->setPath($redirectUrlSuccess);
        } catch (OrderAlreadyProcessingException $e) {
            // Order is already being processed (by IPN or admin update button)
            // Redirect to success page
            // No need to log as it is not an error case
            return $resultRedirect->setPath($redirectUrlSuccess);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return $resultRedirect->setPath($redirectUrlSuccess);
        }
    }
}
