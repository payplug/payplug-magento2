<?php

namespace Payplug\Payments\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Framework\Exception\PaymentException;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Exception\OrderAlreadyProcessingException;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Psr\Log\LoggerInterface;

class SendNewPaymentLink extends \Magento\Sales\Controller\Adminhtml\Order
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
        $formData = $this->getRequest()->getParam('form');
        if ($formData === null) {
            return $this->_redirect('sales/order');
        }
        $params = $this->getRequest()->getParams();
        $params['order_id'] = $formData['order_id'];
        $this->getRequest()->setParams($params);

        if ($order = $this->_initOrder()) {
            if (!$this->payplugHelper->canSendNewPaymentLink($order)) {
                $this->messageManager->addErrorMessage(__('A new payment link cannot be sent for this order.'));

                return $this->_redirect('sales/order/view', ['order_id' => $order->getId()]);
            }

            try {
                $order = $this->payplugHelper->sendNewPaymentLink($order, $formData);
                $this->messageManager->addSuccessMessage(__('New payment link was successfully sent.'));
            } catch (PaymentException $e) {
                $this->payplugLogger->error($e->getMessage());
                $this->messageManager->addErrorMessage($e->getMessage());

                $this->_getSession()->setPaymentLinkFormData($formData);

                return $this->_redirect('payplug_payments_admin/order/newPaymentLinkForm', [
                    'order_id' => $order->getId()
                ]);
            } catch (PayplugException $e) {
                $this->payplugLogger->error($e->__toString());
                $this->messageManager->addErrorMessage(
                    sprintf(__('An error occurred while sending new payment link: %s.'), $e->getMessage())
                );
            } catch (OrderAlreadyProcessingException $e) {
                // Order is already being processed (by payment return controller or IPN)
                // No need to log as it is not an error case
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->payplugLogger->error($e->getMessage());
                $this->messageManager->addErrorMessage(
                    sprintf(__('An error occurred while sending new payment link: %s.'), $e->getMessage())
                );
            }

            return $this->_redirect('sales/order/view', ['order_id' => $order->getId()]);
        }

        return $this->_redirect('sales/order');
    }
}
