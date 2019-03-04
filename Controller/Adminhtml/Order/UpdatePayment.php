<?php

namespace Payplug\Payments\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Psr\Log\LoggerInterface;

class UpdatePayment extends \Magento\Sales\Controller\Adminhtml\Order
{
    /**
     * @var Logger
     */
    private $payplugLogger;

    /**
     * @var Data
     */
    private $payplugHelper;

    /**
     * @param Action\Context                                   $context
     * @param \Magento\Framework\Registry                      $coreRegistry
     * @param \Magento\Framework\App\Response\Http\FileFactory $fileFactory
     * @param \Magento\Framework\Translate\InlineInterface     $translateInline
     * @param \Magento\Framework\View\Result\PageFactory       $resultPageFactory
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Magento\Framework\View\Result\LayoutFactory     $resultLayoutFactory
     * @param \Magento\Framework\Controller\Result\RawFactory  $resultRawFactory
     * @param OrderManagementInterface                         $orderManagement
     * @param OrderRepositoryInterface                         $orderRepository
     * @param LoggerInterface                                  $logger
     * @param Logger                                           $payplugLogger
     * @param Data                                             $payplugHelper
     */
    public function __construct(
        Action\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Framework\Translate\InlineInterface $translateInline,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\View\Result\LayoutFactory $resultLayoutFactory,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
        OrderManagementInterface $orderManagement,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger,
        Logger $payplugLogger,
        Data $payplugHelper
    ) {
        $this->payplugLogger = $payplugLogger;
        $this->payplugHelper = $payplugHelper;
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
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        if ($order = $this->_initOrder()) {
            if (!$this->payplugHelper->canUpdatePayment($order)) {
                $this->messageManager->addErrorMessage(__('The payment cannot be updated for this order.'));

                return $this->_redirect('sales/order/view', ['order_id' => $order->getId()]);
            }

            try {
                $order = $this->payplugHelper->updateOrder($order);
                $this->messageManager->addSuccessMessage(__('Order payment was successfully updated.'));
            } catch (PayplugException $e) {
                $this->payplugLogger->error($e->__toString());
                $this->messageManager->addErrorMessage(
                    sprintf(__('An error occured while updating the payment: %s.'), $e->getMessage())
                );
            } catch (\Exception $e) {
                $this->payplugLogger->error($e->getMessage());
                $this->messageManager->addErrorMessage(
                    sprintf(__('An error occured while updating the payment: %s.'), $e->getMessage())
                );
            }

            return $this->_redirect('sales/order/view', ['order_id' => $order->getId()]);
        }

        return $this->_redirect('sales/order');
    }
}
