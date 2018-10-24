<?php

namespace Payplug\Payments\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Payplug\Payments\Model\PaymentMethod;
use Psr\Log\LoggerInterface;

class UpdatePayment extends \Magento\Sales\Controller\Adminhtml\Order
{
    /**
     * @var PaymentMethod
     */
    protected $paymentMethod;

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
     * @param PaymentMethod                                    $paymentMethod
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
        PaymentMethod $paymentMethod
    ) {
        $this->paymentMethod = $paymentMethod;
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

            if (!$this->paymentMethod->canUpdatePayment($order)) {
                $this->messageManager->addErrorMessage(__('The payment cannot be updated for this order.'));

                return $this->_redirect('sales/order/view', ['order_id' => $order->getId()]);
            }

            try {
                $this->paymentMethod->updatePayment($order);
                $this->messageManager->addSuccessMessage(__('Order payment was successfully updated.'));
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(sprintf(__('An error occured while updating the payment: %s.'), $e->getMessage()));
            }

            return $this->_redirect('sales/order/view', ['order_id' => $order->getId()]);
        }

        return $this->_redirect('sales/order');
    }
}
