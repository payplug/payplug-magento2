<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Framework\Url\EncoderInterface;

class PlaceOrderExtraParamsRegistry
{
    /**
     * @param EncoderInterface $encoder
     */
    public function __construct(
        private readonly EncoderInterface $encoder
    ) {
    }

    /**
     * @var string|null
     */
    private ?string $maskedQuoteId = null;

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
     * Get masked quote ID.
     *
     * @return string|null
     */
    public function getMaskedQuoteId(): ?string
    {
        return $this->maskedQuoteId;
    }

    /**
     * Set masked quote ID.
     *
     * @param string $maskedQuoteId
     * @return void
     */
    public function setMaskedQuoteId(string $maskedQuoteId): void
    {
        $this->maskedQuoteId = $maskedQuoteId;
    }

    /**
     * Get encoded custom after success URL.
     *
     * @return string|null
     */
    public function getEncodedCustomAfterSuccessUrl(): ?string
    {
        if ($this->customAfterSuccessUrl === null) {
            return null;
        }

        return $this->encoder->encode($this->customAfterSuccessUrl);
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
     * Get encoded custom after failure URL.
     *
     * @return string|null
     */
    public function getEncodedCustomAfterFailureUrl(): ?string
    {
        if ($this->customAfterFailureUrl === null) {
            return null;
        }

        return $this->encoder->encode($this->customAfterFailureUrl);
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
     * Get encoded custom after cancel URL.
     *
     * @return string|null
     */
    public function getEncodedCustomAfterCancelUrl(): ?string
    {
        if ($this->customAfterCancelUrl === null) {
            return null;
        }

        return $this->encoder->encode($this->customAfterCancelUrl);
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
