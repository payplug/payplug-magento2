<?php

namespace Payplug\Payments\Controller\Payment;

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

    public function execute()
    {
        $redirectUrlSuccess = 'checkout/onepage/success';
        try {
            $lastIncrementId = $this->getCheckout()->getLastRealOrderId();

            if (!$lastIncrementId) {
                $this->logger->error('Could not retrieve last order id');
                return $this->_redirect($redirectUrlSuccess);
            }
            $order = $this->salesOrderFactory->create();
            $order->loadByIncrementId($lastIncrementId);

            if (!$order->getId()) {
                $this->logger->error(sprintf('Could not retrieve order with id %s', $lastIncrementId));
                return $this->_redirect($redirectUrlSuccess);
            }

            $this->payplugHelper->checkPaymentFailureAndAbortPayment($order);
            $order = $this->payplugHelper->updateOrder($order);

            if ($this->payplugHelper->isOrderValidated($order)) {
                return $this->_redirect($redirectUrlSuccess);
            } else {
                $this->_forward('cancel', null, null, ['is_canceled_by_provider' => true]);
            }
        } catch (PayplugException $e) {
            $this->logger->error($e->__toString());

            return $this->_redirect($redirectUrlSuccess);
        } catch (OrderAlreadyProcessingException $e) {
            // Order is already being processed (by IPN or admin update button)
            // Redirect to success page
            // No need to log as it is not an error case
            return $this->_redirect($redirectUrlSuccess);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return $this->_redirect($redirectUrlSuccess);
        }
    }
}
