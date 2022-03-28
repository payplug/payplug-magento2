<?php

namespace Payplug\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Sales\Model\Order;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Gateway\Config\Ondemand;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;

class OrderCancelObserver implements ObserverInterface
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param Data   $helper
     * @param Logger $logger
     */
    public function __construct(Data $helper, Logger $logger)
    {
        $this->helper = $helper;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function execute(EventObserver $observer)
    {
        /** @var Order $creditMemo */
        $order = $observer->getData('order');
        if ($order->getPayment() === false) {
            return;
        }

        if ($order->getPayment()->getMethod() != Ondemand::METHOD_CODE) {
            return;
        }

        $lastOrderPayment = $this->helper->getOrderLastPayment($order->getIncrementId());
        if ($lastOrderPayment === null) {
            $this->logger->error(
                sprintf('No payplug payment was found for order %s using payment request', $order->getIncrementId())
            );
            return;
        }

        try {
            $lastOrderPayment->abort($order->getStoreId());
        } catch (PayplugException $e) {
            $this->logger->error($e->__toString());
            throw new \Exception(__('Unable to abort payment %1', $lastOrderPayment->getPaymentId()));
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new \Exception(__('Unable to abort payment %1', $lastOrderPayment->getPaymentId()));
        }
    }
}
