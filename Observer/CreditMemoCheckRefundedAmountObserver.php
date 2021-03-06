<?php

namespace Payplug\Payments\Observer;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Gateway\Config\Oney;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;

class CreditMemoCheckRefundedAmountObserver implements ObserverInterface
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var Http
     */
    private $request;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @param Data             $helper
     * @param ManagerInterface $messageManager
     * @param Logger           $logger
     * @param Http             $request
     * @param OrderRepository  $orderRepository
     */
    public function __construct(
        Data $helper,
        ManagerInterface $messageManager,
        Logger $logger,
        Http $request,
        OrderRepository $orderRepository
    ) {
        $this->helper = $helper;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
        $this->request = $request;
        $this->orderRepository = $orderRepository;
    }

    public function execute(EventObserver $observer)
    {
        // Online refunds are only available when creating a credit memo from an invoice
        if (!$this->request->getParam('invoice_id')) {
            return;
        }
        // We only need to display messages for new credit memos
        if ($this->request->getParam('creditmemo_id')) {
            return;
        }

        try {
            $order = $this->orderRepository->get($this->request->getParam('order_id'));
            $payplugPayment = $this->helper->getOrderPayment($order->getIncrementId());
            $payment = $payplugPayment->retrieve($order->getStoreId());

            if ($order->getPayment()->getMethod() === Oney::METHOD_CODE) {
                $oneyDelay = 48;
                $allowedOneyRefundDate = (new \DateTime())->modify('- ' . $oneyDelay . ' hours');
                if ($this->getLastUpdateOnOrder($order) >= $allowedOneyRefundDate) {
                    $this->messageManager->addErrorMessage(
                        __('The refund can be made %1 hours after the payment or %1 hours after the last refund.', $oneyDelay)
                    );

                    return;
                }
            }

            $refundedAmount = $payment->amount_refunded / 100;
            if ($refundedAmount == 0) {
                return;
            }

            if ((float)$order->getTotalRefunded() == (float)$refundedAmount) {
                return;
            }

            $paidAmount = $payment->amount / 100;
            $maximumAmount = min(
                (float)($paidAmount - $refundedAmount),
                (float)($paidAmount - $order->getTotalRefunded())
            );

            $maximumAmount = $order->formatPriceTxt($maximumAmount);
            $this->messageManager->addErrorMessage(
                sprintf(__('Some refunds were made directly in Payplug. Maximum refund amount is %s.'), $maximumAmount)
            );
        } catch (NoSuchEntityException $e) {
            // Could not retrieve payplug payment from order / Meaning payplug was not used for this order
        } catch (PayplugException $e) {
            $this->logger->error($e->__toString());
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * @param Order $order
     *
     * @return \DateTime
     */
    private function getLastUpdateOnOrder(Order $order)
    {
        $lastUpdate = new \DateTime($order->getCreatedAt());
        /** @var Order\Creditmemo $creditmemo */
        foreach ($order->getCreditmemosCollection() as $creditmemo) {
            if (!$creditmemo->getInvoiceId()) {
                continue;
            }
            if (!$creditmemo->getTransactionId()) {
                continue;
            }
            $creditmemoDate = new \DateTime($creditmemo->getCreatedAt());
            if ($creditmemoDate > $lastUpdate) {
                $lastUpdate = $creditmemoDate;
            }
        }

        return $lastUpdate;
    }
}
