<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Block\Adminhtml\Config\Field;

use Magento\Framework\Data\Form\Element\CollectionFactory;
use Magento\Framework\Data\Form\Element\Factory;
use Magento\Framework\Data\Form\Element\Text;
use Magento\Framework\Escaper;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Payplug\Payments\Helper\Config as PayplugConfigHelper;

class Threshold extends Text
{
    /**
     * @param PayplugConfigHelper $payplugConfigHelper
     * @param PriceCurrencyInterface $priceCurrency
     * @param Factory $factoryElement
     * @param CollectionFactory $factoryCollection
     * @param Escaper $escaper
     * @param array $data
     */
    public function __construct(
        private readonly PayplugConfigHelper $payplugConfigHelper,
        private readonly PriceCurrencyInterface $priceCurrency,
        Factory $factoryElement,
        CollectionFactory $factoryCollection,
        Escaper $escaper,
        array $data = []
    ) {
        parent::__construct($factoryElement, $factoryCollection, $escaper, $data);
    }

    /**
     * Get HTML
     *
     * @return mixed
     */
    public function getHtml(): mixed
    {
        if (!preg_match('/groups\[([^]]+)]\[/', $this->getData('name'), $matches)) {
            return parent::getHtml();
        }

        $paymentMethod = str_replace('payplug_payments_', '', $matches[1]);
        $paymentMethod = $paymentMethod === 'oney_without_fees' ? 'oney' : $paymentMethod;

        $minAmountConfigValue = $this->payplugConfigHelper->getConfigValue($paymentMethod . '_min_amounts') ?: null;
        $maxAmountConfigValue = $this->payplugConfigHelper->getConfigValue($paymentMethod . '_max_amounts') ?: null;

        if ($minAmountConfigValue === null || $maxAmountConfigValue === null) {
            return parent::getHtml();
        }

        list($minCurrency, $minAmount) = explode(':', $minAmountConfigValue);
        list($maxCurrency, $maxAmount) = explode(':', $maxAmountConfigValue);

        $minAmount = (float) ($minAmount / 100);
        $maxAmount = (float) ($maxAmount / 100);

        $precision = PriceCurrencyInterface::DEFAULT_PRECISION;
        $formatedMinAmount = $this->priceCurrency->convertAndFormat($minAmount, false, $precision, null, $minCurrency);
        $formatedMaxAmount = $this->priceCurrency->convertAndFormat($maxAmount, false, $precision, null, $maxCurrency);

        $this->addClass(sprintf('validate-number validate-number-range number-range-%d-%d', $minAmount, $maxAmount));
        $this->setData(
            'comment',
            __('The amount must be between %1 and %2.', $formatedMinAmount, $formatedMaxAmount)->render()
        );

        return parent::getHtml();
    }
}
