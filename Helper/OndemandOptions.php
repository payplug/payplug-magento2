<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class OndemandOptions extends AbstractHelper
{
    /**
     * Get sent by options
     *
     * @return array
     */
    public function getAvailableOndemandSentBy()
    {
        return [
            \Payplug\Payments\Model\Order\Payment::SENT_BY_SMS   => __('SMS'),
            \Payplug\Payments\Model\Order\Payment::SENT_BY_EMAIL => __('Email'),
        ];
    }

    /**
     * Get language options
     *
     * @return array
     */
    public function getAvailableOndemandLanguage()
    {
        return [
            'fr' => __('French'),
            'en' => __('English'),
            'it' => __('Italian'),
        ];
    }
}
