<?php

namespace Payplug\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Payplug\Exception\PayplugException;
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
     * @param Data             $helper
     * @param ManagerInterface $messageManager
     * @param Logger           $logger
     */
    public function __construct(Data $helper, ManagerInterface $messageManager, Logger $logger)
    {
        $this->helper = $helper;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
    }

    public function execute(EventObserver $observer)
    {
        /** @var Creditmemo $creditMemo */
        $creditMemo = $observer->getData('creditmemo');

        if ($creditMemo === false || $creditMemo->getId()) {
            return;
        }

        $order = $creditMemo->getOrder();

        try {
            $payplugPayment = $this->helper->getOrderPayment($order->getIncrementId());
            $payment = $payplugPayment->retrieve($order->getStoreId());

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
}
