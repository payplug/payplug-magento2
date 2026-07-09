<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Block\Customer;

use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Element\Template\Context;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\AbstractTokenRenderer;
use Payplug\Payments\Gateway\Config\Standard;

class CardRenderer extends AbstractTokenRenderer
{
    /**
     * @param Repository $assetRepository
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        private readonly Repository $assetRepository,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Can renderer token
     *
     * @param PaymentTokenInterface $token
     * @return bool
     */
    public function canRender(PaymentTokenInterface $token): bool
    {
        return $token->getPaymentMethodCode() === Standard::METHOD_CODE;
    }

    /**
     * Get the last 4 digits of the credit card number
     *
     * @return string
     */
    public function getNumberLast4Digits(): string
    {
        return $this->getTokenDetails()['masked_cc'] ?? '';
    }

    /**
     * Get the expiration date of the credit card
     *
     * @return string
     */
    public function getExpDate(): string
    {
        return $this->getTokenDetails()['exp_date'] ?? '';
    }

    /**
     * Get credit card issuer icon url
     *
     * @return string
     */
    public function getIconUrl(): string
    {
        $fileId = 'Payplug_Payments::images/standard/' . ($this->getTokenDetails()['brand'] ?? 'other') . '.svg';

        return $this->assetRepository->getUrl($fileId);
    }

    /**
     * Get credit card issuer icon height
     *
     * @return string
     */
    public function getIconHeight(): string
    {
        return '';
    }

    /**
     * Get credit card issuer icon width
     *
     * @return string
     */
    public function getIconWidth(): string
    {
        return '';
    }
}
