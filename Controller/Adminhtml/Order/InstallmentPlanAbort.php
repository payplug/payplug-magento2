<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
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
use Magento\Sales\Controller\Adminhtml\Order;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Psr\Log\LoggerInterface;

class InstallmentPlanAbort extends Order
{
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
     * Abort installment plan
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
            if (!$this->payplugHelper->canAbortInstallmentPlan($order)) {
                $this->messageManager->addErrorMessage(__('The installment plan cannot be aborted for this order.'));

                return $resultRedirect->setPath('sales/order/view', ['order_id' => $order->getId()]);
            }

            try {
                $this->payplugHelper->cancelInstallmentPlan($order);
                // Force order save in order to check the order state
                $this->orderRepository->save($order);
                $this->payplugHelper->refreshSalesGrid($order->getId());
                $this->messageManager->addSuccessMessage(__('Installment plan was successfully aborted.'));
            } catch (PayplugException $e) {
                $this->payplugLogger->error($e->__toString());
                $this->messageManager->addErrorMessage(
                    sprintf((string)__('An error occurred while aborting the installment plan: %s.'), $e->getMessage())
                );
            } catch (\Exception $e) {
                $this->payplugLogger->error($e->getMessage());
                $this->messageManager->addErrorMessage(
                    sprintf((string)__('An error occurred while aborting the installment plan: %s.'), $e->getMessage())
                );
            }

            return $resultRedirect->setPath('sales/order/view', ['order_id' => $order->getId()]);
        }

        return $resultRedirect->setPath('sales/order');
    }
}
