<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\OrderFactory;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;

abstract class AbstractPayment extends Action
{
    public function __construct(
        Context $context,
        protected Session $checkoutSession,
        protected OrderFactory $salesOrderFactory,
        protected Logger $logger,
        protected Data $payplugHelper
    ) {
        parent::__construct($context);
    }

    /**
     * Get quote
     *
     * @return CartInterface|Quote
     */
    protected function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }

    /**
     * Get checkout session namespace
     */
    protected function getCheckout(): Session
    {
        return $this->checkoutSession;
    }
}
