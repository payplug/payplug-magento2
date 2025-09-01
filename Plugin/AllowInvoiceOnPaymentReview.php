<?php

declare(strict_types=1);

namespace Payplug\Payments\Plugin;

use Magento\Sales\Model\Order;
use Payplug\Payments\Helper\Data as PayplugHelper;

class AllowInvoiceOnPaymentReview
{
    public function __construct(
        private readonly PayplugHelper $payplugHelper
    ) {
    }

    public function afterCanInvoice(Order $subject, bool $result): bool
    {
        $paymentMethod = $subject->getPayment()->getMethod();

        if ($this->payplugHelper->isCodePayplugPayment($paymentMethod) && $subject->isPaymentReview()) {
            return true;
        }

        return $result;
    }
}
