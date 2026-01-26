<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Controller\Adminhtml\Order;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\Registry;
use Magento\Framework\Translate\InlineInterface;
use Magento\Framework\View\Result\LayoutFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Controller\Adminhtml\Order as AdminOrder;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Exception\OrderAlreadyProcessingException;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger as PayplugLogger;
use Psr\Log\LoggerInterface;

class SendNewPaymentLink extends AdminOrder
{
    /**
     * @param PayplugLogger $payplugLogger
     * @param Data $payplugHelper
     * @param Validator $formKeyValidator
     * @param RequestInterface $request
     * @param FormKey $formKey
     * @param Context $context
     * @param Registry $coreRegistry
     * @param FileFactory $fileFactory
     * @param InlineInterface $translateInline
     * @param PageFactory $resultPageFactory
     * @param JsonFactory $resultJsonFactory
     * @param LayoutFactory $resultLayoutFactory
     * @param RawFactory $resultRawFactory
     * @param OrderManagementInterface $orderManagement
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly PayplugLogger $payplugLogger,
        private readonly Data $payplugHelper,
        private readonly Validator $formKeyValidator,
        private readonly RequestInterface $request,
        private readonly FormKey $formKey,
        Context $context,
        Registry $coreRegistry,
        FileFactory $fileFactory,
        InlineInterface $translateInline,
        PageFactory $resultPageFactory,
        JsonFactory $resultJsonFactory,
        LayoutFactory $resultLayoutFactory,
        RawFactory $resultRawFactory,
        OrderManagementInterface $orderManagement,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        parent::__construct(
            $context,
            $coreRegistry,
            $fileFactory,
            $translateInline,
            $resultPageFactory,
            $resultJsonFactory,
            $resultLayoutFactory,
            $resultRawFactory,
            $orderManagement,
            $orderRepository,
            $logger
        );
    }

    /**
     * OnDemand new payment link send
     *
     * @return Redirect
     * @throws LocalizedException
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        $formKeyValidation = $this->formKeyValidator->validate($this->request);
        if (!$formKeyValidation) {
            $this->messageManager->addErrorMessage(
                __('Your session has expired')
            );

            return $resultRedirect->setPath('*/*/');
        }

        $formData = $this->getRequest()->getParam('form');
        if ($formData === null) {
            return $resultRedirect->setPath('sales/order');
        }
        $params = $this->getRequest()->getParams();
        $params['order_id'] = $formData['order_id'];
        $this->getRequest()->setParams($params);

        if ($order = $this->_initOrder()) {
            if (!$this->payplugHelper->canSendNewPaymentLink($order)) {
                $this->messageManager->addErrorMessage(__('A new payment link cannot be sent for this order.'));

                return $resultRedirect->setPath('sales/order/view', ['order_id' => $order->getId()]);
            }

            try {
                $order = $this->payplugHelper->sendNewPaymentLink($order, $formData);
                $this->messageManager->addSuccessMessage(__('New payment link was successfully sent.'));
            } catch (PaymentException $e) {
                $this->payplugLogger->error($e->getMessage());
                $this->messageManager->addErrorMessage($e->getMessage());

                $this->_getSession()->setPaymentLinkFormData($formData);

                return $resultRedirect->setPath('payplug_payments_admin/order/newPaymentLinkForm', [
                    'order_id' => $order->getId(),
                    'form_key' => $this->formKey->getFormKey() ?: ''
                ]);
            } catch (PayplugException $e) {
                $this->payplugLogger->error($e->__toString());
                $this->messageManager->addErrorMessage(
                    sprintf((string)__('An error occurred while sending new payment link: %s.'), $e->getMessage())
                );
            } catch (OrderAlreadyProcessingException $e) {
                // Order is already being processed (by payment return controller or IPN)
                // No need to log as it is not an error case
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (Exception $e) {
                $this->payplugLogger->error($e->getMessage());
                $this->messageManager->addErrorMessage(
                    sprintf((string)__('An error occurred while sending new payment link: %s.'), $e->getMessage())
                );
            }

            return $resultRedirect->setPath('sales/order/view', ['order_id' => $order->getId()]);
        }

        return $resultRedirect->setPath('sales/order');
    }
}
