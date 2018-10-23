<?php

namespace Payplug\Payments\Controller\Payment;

use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Model\PaymentMethod;

abstract class AbstractPayment extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $salesOrderFactory;

    /**
     * @var PaymentMethod
     */
    protected $paymentMethod;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var Data
     */
    protected $payplugHelper;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session       $checkoutSession
     * @param \Magento\Sales\Model\OrderFactory     $salesOrderFactory
     * @param PaymentMethod                         $paymentMethod
     * @param Logger                                $logger
     * @param Data                                  $payplugHelper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $salesOrderFactory,
        PaymentMethod $paymentMethod,
        Logger $logger,
        Data $payplugHelper
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->salesOrderFactory = $salesOrderFactory;
        $this->paymentMethod = $paymentMethod;
        $this->logger = $logger;
        $this->payplugHelper = $payplugHelper;
    }

    protected function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }

    /**
     * Get checkout session namespace
     *
     * @return \Magento\Checkout\Model\Session
     */
    protected function getCheckout()
    {
        return $this->checkoutSession;
    }
}
