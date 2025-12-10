<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Registry;
use Magento\Framework\Translate\InlineInterface;
use Magento\Framework\View\Result\LayoutFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Controller\Adminhtml\Order as AdminOrder;
use Magento\Sales\Model\Order;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Exception\OrderAlreadyProcessingException;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Psr\Log\LoggerInterface;

class UpdatePayment extends AdminOrder
{
    /**
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
     * @param Logger $payplugLogger
     * @param Data $payplugHelper
     * @param Validator $formKeyValidator
     * @param RequestInterface $request
     */
    public function __construct(
        Action\Context $context,
        Registry $coreRegistry,
        FileFactory $fileFactory,
        InlineInterface $translateInline,
        PageFactory $resultPageFactory,
        JsonFactory $resultJsonFactory,
        LayoutFactory $resultLayoutFactory,
        RawFactory $resultRawFactory,
        OrderManagementInterface $orderManagement,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger,
        private Logger $payplugLogger,
        private Data $payplugHelper,
        private Validator $formKeyValidator,
        private RequestInterface $request
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
     * Update PayPlug payment data
     *
     * @return Redirect
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

        if ($order = $this->_initOrder()) {
            if (!$this->payplugHelper->canUpdatePayment($order)) {
                $this->messageManager->addErrorMessage(__('The payment cannot be updated for this order.'));

                return $resultRedirect->setPath('sales/order/view', ['order_id' => $order->getId()]);
            }

            try {
                $order = $this->payplugHelper->updateOrder($order);
                $this->messageManager->addSuccessMessage(__('Order payment was successfully updated.'));
            } catch (PayplugException $e) {
                $this->payplugLogger->error($e->__toString());
                $this->messageManager->addErrorMessage(
                    sprintf((string)__('An error occurred while updating the payment: %s.'), $e->getMessage())
                );
            } catch (OrderAlreadyProcessingException $e) {
                // Order is already being processed (by payment return controller or IPN)
                // No need to log as it is not an error case
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->payplugLogger->error($e->getMessage());
                $this->messageManager->addErrorMessage(
                    sprintf((string)__('An error occurred while updating the payment: %s.'), $e->getMessage())
                );
            }

            return $resultRedirect->setPath('sales/order/view', ['order_id' => $order->getId()]);
        }

        return $resultRedirect->setPath('sales/order');
    }
}
