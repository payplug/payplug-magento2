<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\ViewModel;

use Throwable;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Payplug\Payments\Gateway\Config\OneyWithoutFees;
use Payplug\Payments\Helper\Oney as OneyHelper;
use Payplug\Payments\Logger\Logger;

class Oney implements ArgumentInterface
{
    /**
     * @param OneyHelper $oneyHelper
     * @param CheckoutSession $checkoutSession
     * @param JsonHexTag $jsonHexTag
     * @param Logger $logger
     */
    public function __construct(
        private readonly OneyHelper $oneyHelper,
        private readonly CheckoutSession $checkoutSession,
        private readonly JsonHexTag $jsonHexTag,
        private readonly Logger $logger
    ) {
    }

    /**
     * Can display Oney call to action
     *
     * @return bool
     */
    public function canDisplayOney(): bool
    {
        try {
            return $this->oneyHelper->canDisplayOney();
        } catch (Throwable $e) {
            $this->logger->error('Could not determine Oney availability', ['exception' => $e]);

            return false;
        }
    }

    /**
     * Get Oney widget configuration for a given amount and Oney payment method (the available one by default)
     *
     * @param float|null $amount
     * @param string|null $paymentMethod
     * @return array
     */
    public function getConfig(?float $amount = null, ?string $paymentMethod = null): array
    {
        try {
            return $this->oneyHelper->getWidgetConfig($amount, $paymentMethod);
        } catch (Throwable $e) {
            $this->logger->error('Could not build Oney widget configuration', ['exception' => $e]);

            return [];
        }
    }

    /**
     * Get Oney widget configuration as JSON for the frontend widget
     *
     * @param array $config
     * @param bool $isProduct
     * @return string
     */
    public function getJsConfig(array $config, bool $isProduct): string
    {
        return $this->jsonHexTag->serialize(['is_product' => $isProduct] + $config);
    }

    /**
     * Is the configured Oney payment method the one without fees
     *
     * @param array $config
     * @return bool
     */
    public function isWithoutFees(array $config): bool
    {
        return ($config['payment_method'] ?? '') === OneyWithoutFees::METHOD_CODE;
    }

    /**
     * Get the message displayed when the amount is out of the Oney range
     *
     * @return string
     */
    public function getAmountRangeMessage(): string
    {
        try {
            return $this->oneyHelper->getAmountRangeMessage();
        } catch (Throwable $e) {
            $this->logger->error('Could not build Oney amount range message', ['exception' => $e]);

            return '';
        }
    }

    /**
     * Is current store locale Italian
     *
     * @return bool
     */
    public function isItalianStore(): bool
    {
        return $this->oneyHelper->isItalianStore();
    }

    /**
     * Get current cart base grand total
     *
     * @return float
     */
    public function getCartAmount(): float
    {
        try {
            return (float) $this->checkoutSession->getQuote()->getBaseGrandTotal();
        } catch (Throwable $e) {
            $this->logger->error('Could not retrieve cart amount for Oney widget', ['exception' => $e]);

            return 0.0;
        }
    }
}
