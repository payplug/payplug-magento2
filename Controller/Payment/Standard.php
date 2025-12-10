<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\Payment;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\PaymentException;
use Magento\Sales\Model\OrderFactory;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Service\GetCurrentOrder;
use Throwable;

class Standard extends AbstractPayment
{
    /**
     * @param Context $context
     * @param Session $checkoutSession
     * @param OrderFactory $salesOrderFactory
     * @param Logger $logger
     * @param Data $payplugHelper
     * @param FormKey $formKey
     * @param GetCurrentOrder $getCurrentOrder
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderFactory $salesOrderFactory,
        Logger $logger,
        Data $payplugHelper,
        protected FormKey $formKey,
        protected GetCurrentOrder $getCurrentOrder
    ) {
        parent::__construct($context, $checkoutSession, $salesOrderFactory, $logger, $payplugHelper);
    }

    /**
     * Retrieve PayPlug Standard payment url
     *
     * @return Redirect|ResultInterface|Json
     * @throws LocalizedException
     */
    public function execute()
    {
        $shouldRedirect = $this->getRequest()->getParam('should_redirect', true);

        /** @var Json $response */
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $responseParams = [
            'url' => $this->_url->getUrl('payplug_payments/payment/cancel', [
                'is_canceled_by_provider' => true,
                'form_key' => $this->formKey->getFormKey() ?: ''
            ]),
            'error' => true,
            'message' => __('An error occurred while processing the order.')
        ];

        try {
            $order = $this->getCurrentOrder->execute();
            $url = $order->getPayment()->getAdditionalInformation('payment_url');
            $order->getPayment()->unsAdditionalInformation('payment_url');
            $isPaid = (bool)$order->getPayment()->getAdditionalInformation('is_paid', false);
            $order->getPayment()->unsAdditionalInformation('is_paid');
            if ($isPaid) {
                $response->setData([
                    'is_paid' => true,
                    'error' => false,
                ]);

                return $response;
            }

            if ($this->getRequest()->getParam('integrated')) {
                $paymentId = $order->getPayment()->getAdditionalInformation('payplug_payment_id');
                $order->getPayment()->unsAdditionalInformation('payplug_payment_id');

                if (empty($paymentId)) {
                    throw new Exception('Could not retrieve payment id for integrated payment');
                }
                $response->setData([
                    'payment_id' => $paymentId,
                    'error' => false,
                ]);

                return $response;
            }

            if (empty($url)) {
                throw new Exception('Could not retrieve payment url');
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
                    __('An error occurred while processing your payment. Please try again.')
                );
                return $this->resultRedirectFactory->create()->setPath(
                    'payplug_payments/payment/cancel',
                    [
                        'is_canceled_by_provider' => true,
                        'form_key' => $this->formKey->getFormKey() ?: ''
                    ]
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
                    [
                        'is_canceled_by_provider' => true,
                        'form_key' => $this->formKey->getFormKey() ?: ''
                    ]
                );
            }

            $response->setData($responseParams);

            return $response;
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage());

            if ($shouldRedirect) {
                $this->messageManager->addErrorMessage(
                    __('An error occurred while processing your payment. Please try again.')
                );
                return $this->resultRedirectFactory->create()->setPath(
                    'payplug_payments/payment/cancel',
                    [
                        'is_canceled_by_provider' => true,
                        'form_key' => $this->formKey->getFormKey() ?: ''
                    ]
                );
            }

            $response->setData($responseParams);

            return $response;
        }
    }
}
