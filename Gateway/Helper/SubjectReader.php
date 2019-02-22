<?php

namespace Payplug\Payments\Gateway\Helper;

use Magento\Checkout\Model\Session\Proxy;

class SubjectReader extends \Magento\Payment\Gateway\Helper\SubjectReader
{
    /**
     * @var Proxy
     */
    private $checkoutSession;

    /**
     * SubjectReader constructor.
     *
     * @param Proxy $checkoutSession
     */
    public function __construct(Proxy $checkoutSession)
    {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @return \Magento\Quote\Model\Quote
     */
    public function getQuote()
    {
        return $this->checkoutSession->getQuote();
    }
}
