<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\OrderRepository;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;

class Cancel extends AbstractPayment
{
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderFactory $salesOrderFactory,
        Logger $logger,
        Data $payplugHelper,
        private OrderRepository $orderRepository,
        private Validator $formKeyValidator,
        private RequestInterface $request
    ) {
        parent::__construct($context, $checkoutSession, $salesOrderFactory, $logger, $payplugHelper);
    }

    /**
     * Cancel PayPlug payment
     *
     * @return mixed
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $redirectUrlCart = 'checkout/cart';

        $formKeyValidation = $this->formKeyValidator->validate($this->request);
        if (!$formKeyValidation) {
            $this->messageManager->addErrorMessage(
                __('Your session has expired')
            );

            return $resultRedirect->setPath($redirectUrlCart);
        }

        try {
            $lastIncrementId = $this->getCheckout()->getLastRealOrderId();

            if (!$lastIncrementId) {
                $this->logger->error('Could not retrieve last order id');

                return $resultRedirect->setPath($redirectUrlCart);
            }
            $order = $this->salesOrderFactory->create();
            /** @var $order Order */
            $order->loadByIncrementId($lastIncrementId);

            if (!$order->getId()) {
                $this->logger->error(sprintf('Could not retrieve order with id %s', $lastIncrementId));

                return $resultRedirect->setPath($redirectUrlCart);
            }

            $this->payplugHelper->cancelOrderAndInvoice($order);

            $failureMessage = $this->_request->getParam(
                'failure_message',
                __('The transaction was aborted and your card has not been charged')
            );
            if (!empty($failureMessage)) {
                $this->messageManager->addErrorMessage($failureMessage);
            }

            $this->getCheckout()->restoreQuote();

            return $resultRedirect->setPath($redirectUrlCart);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return $resultRedirect->setPath($redirectUrlCart);
        }
    }

}
