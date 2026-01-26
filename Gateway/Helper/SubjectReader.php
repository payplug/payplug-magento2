<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Gateway\Helper;

use Magento\Checkout\Model\Session;

class SubjectReader extends \Magento\Payment\Gateway\Helper\SubjectReader
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * SubjectReader constructor.
     *
     * @param Session $checkoutSession
     */
    public function __construct(Session $checkoutSession)
    {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Get quote
     *
     * @return \Magento\Quote\Model\Quote
     */
    public function getQuote()
    {
        return $this->checkoutSession->getQuote();
    }
}
