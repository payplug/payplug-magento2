<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Service;

class PlaceOrderExtraParamsRegistry
{
    /**
     * @var string|null
     */
    private ?string $quoteId = null;

    /**
     * @var string|null
     */
    private ?string $customAfterSuccessUrl = null;

    /**
     * @var string|null
     */
    private ?string $customAfterFailureUrl = null;

    /**
     * @var string|null
     */
    private ?string $customAfterCancelUrl = null;

    /**
     * Get quote ID.
     *
     * @return string|null
     */
    public function getQuoteId(): ?string
    {
        return $this->quoteId;
    }

    /**
     * Set quote ID.
     *
     * @param string $quoteId
     * @return void
     */
    public function setQuoteId(string $quoteId): void
    {
        $this->quoteId = $quoteId;
    }

    /**
     * Get custom after success URL.
     *
     * @return string|null
     */
    public function getCustomAfterSuccessUrl(): ?string
    {
        return $this->customAfterSuccessUrl;
    }

    /**
     * Set custom after success URL.
     *
     * @param string|null $afterSuccessUrl
     * @return void
     */
    public function setCustomAfterSuccessUrl(?string $afterSuccessUrl): void
    {
        $this->customAfterSuccessUrl = $afterSuccessUrl;
    }

    /**
     * Get custom after failure URL.
     *
     * @return string|null
     */
    public function getCustomAfterFailureUrl(): ?string
    {
        return $this->customAfterFailureUrl;
    }

    /**
     * Set custom after failure URL.
     *
     * @param string|null $afterFailureUrl
     * @return void
     */
    public function setCustomAfterFailureUrl(?string $afterFailureUrl): void
    {
        $this->customAfterFailureUrl = $afterFailureUrl;
    }

    /**
     * Get custom after cancel URL.
     *
     * @return string|null
     */
    public function getCustomAfterCancelUrl(): ?string
    {
        return $this->customAfterCancelUrl;
    }

    /**
     * Set custom after cancel URL.
     *
     * @param string|null $afterCancelUrl
     * @return void
     */
    public function setCustomAfterCancelUrl(?string $afterCancelUrl): void
    {
        $this->customAfterCancelUrl = $afterCancelUrl;
    }
}
