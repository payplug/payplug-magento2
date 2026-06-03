<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use Payplug\Payments\Logger\Logger as PayplugLogger;
use Throwable;

class GetMaskedQuoteId
{
    /**
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param QuoteIdMaskResource $quoteIdMaskResource
     * @param PayplugLogger $payplugLogger
     */
    public function __construct(
        private readonly QuoteIdMaskFactory $quoteIdMaskFactory,
        private readonly QuoteIdMaskResource $quoteIdMaskResource,
        private readonly PayplugLogger $payplugLogger
    ) {
    }

    /**
     * Get or create masked quote ID
     *
     * @param int $quoteId
     * @return string|null
     */
    public function execute(int $quoteId): ?string
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create();
        $this->quoteIdMaskResource->load($quoteIdMask, $quoteId, 'quote_id');

        if (!$quoteIdMask->getId()) {
            $quoteIdMask->setData('quote_id', $quoteId);
            try {
                $this->quoteIdMaskResource->save($quoteIdMask);
            } catch (Throwable) {
                $this->payplugLogger->error('Could not save quote id mask');
                return null;
            }
        }

        return $quoteIdMask->getMaskedId();
    }
}
