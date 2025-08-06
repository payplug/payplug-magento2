<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\Payment;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Sales\Model\OrderFactory;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Service\GetCurrentOrder;

class Cancel extends AbstractPayment
{
    public function __construct(
        private readonly Validator $formKeyValidator,
        private readonly RequestInterface $request,
        private readonly GetCurrentOrder $getCurrentOrder,
        Context $context,
        Session $checkoutSession,
        OrderFactory $salesOrderFactory,
        Logger $logger,
        Data $payplugHelper
    ) {
        parent::__construct($context, $checkoutSession, $salesOrderFactory, $logger, $payplugHelper);
    }

    public function execute(): Redirect
    {
        $formKeyValidation = $this->formKeyValidator->validate($this->request);
        if (!$formKeyValidation) {
            $this->messageManager->addErrorMessage(
                __('Your session has expired')
            );

            return $this->getResultRedirect();
        }

        try {
            $order = $this->getCurrentOrder->execute();

            $this->payplugHelper->cancelOrderAndInvoice($order);

            $failureMessage = $this->_request->getParam(
                'failure_message',
                __('The transaction was aborted and your card has not been charged')
            );
            if (!empty($failureMessage)) {
                $this->messageManager->addErrorMessage($failureMessage);
            }

            $this->getCheckout()->restoreQuote();

            return $this->getResultRedirect();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());

            return $this->getResultRedirect();
        }
    }

    private function getResultRedirect(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($this->request->getParam('redirectToReferer') == 1) {
            $resultRedirect->setRefererUrl();
        } else {
            $resultRedirect->setPath('checkout/cart');
        }

        return $resultRedirect;
    }

}
