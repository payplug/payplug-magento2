<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Hyva\CheckoutPayplug\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Payplug\Payments\Helper\Oney;

class OneyDisplayer implements ArgumentInterface
{
    public function __construct(
        private Oney $oney
    ) {
    }

    public function canDisplayOney(): bool
    {
        return $this->oney->canDisplayOney();
    }
}
