<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Block\Adminhtml\Config\Field;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\Element\CollectionFactory;
use Magento\Framework\Data\Form\Element\Factory;
use Magento\Framework\Data\Form\Element\Text;
use Magento\Framework\Escaper;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Model\Checks\TotalMinMax;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Model\Payment\AmountBounds;

class OrderTotal extends Text
{
    /**
     * @param AmountBounds $amountBounds
     * @param RequestInterface $request
     * @param Json $json
     * @param Factory $factoryElement
     * @param CollectionFactory $factoryCollection
     * @param Escaper $escaper
     * @param array $data
     */
    public function __construct(
        private readonly AmountBounds $amountBounds,
        private readonly RequestInterface $request,
        private readonly Json $json,
        Factory $factoryElement,
        CollectionFactory $factoryCollection,
        Escaper $escaper,
        array $data = []
    ) {
        parent::__construct($factoryElement, $factoryCollection, $escaper, $data);
    }

    /**
     * Get HTML attributes, with the browser validation of the Payplug limits
     *
     * @return array
     */
    public function getHtmlAttributes(): array
    {
        return array_merge(parent::getHtmlAttributes(), ['data-validate']);
    }

    /**
     * Get HTML, with the order total limits set by Payplug as comment and as browser validation
     *
     * @return mixed
     */
    public function getHtml(): mixed
    {
        if (!preg_match(
            '/^groups\[([^\]]+)\]\[fields\]\[([^\]]+)\]\[value\]$/',
            (string) $this->getData('name'),
            $matches
        )) {
            return parent::getHtml();
        }

        [, $methodCode, $field] = $matches;
        $websiteId = (int) $this->request->getParam('website');
        [$scopeType, $scopeId] = $websiteId
            ? [ScopeInterface::SCOPE_WEBSITES, $websiteId]
            : [ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0];
        $currency = $this->amountBounds->getBaseCurrency($scopeType, $scopeId);
        $accountBounds = $this->amountBounds->getAccountBounds($methodCode, $currency, $scopeType, $scopeId);

        if ($accountBounds === null) {
            return parent::getHtml();
        }

        $min = $this->amountBounds->formatAmount($accountBounds[AmountBounds::MIN], $currency);
        $max = $this->amountBounds->formatAmount($accountBounds[AmountBounds::MAX], $currency);

        $comment = (string) __('Payplug allows from %1 to %2. Leave empty to apply the Payplug limit.', $min, $max);

        $this->setData('data-validate', $this->_escaper->escapeHtmlAttr($this->json->serialize([
            'validate-payplug-order-total' => [
                'min' => $accountBounds[AmountBounds::MIN] / 100,
                'max' => $accountBounds[AmountBounds::MAX] / 100,
                'message' => (string) __('The amount must be between %1 and %2.', $min, $max),
            ],
        ]), false));

        $effectiveBounds = $this->amountBounds->getEffectiveBounds($methodCode, $currency, $scopeType, $scopeId);
        $value = $this->getData('value');

        if ($effectiveBounds === null) {
            $comment .= ' ' . __(
                'No order total fits both your limits and the Payplug limits: this payment method is no longer offered.'
            );
        } elseif (is_numeric($value) && (float) $value > 0) {
            $bound = $field === TotalMinMax::MAX_ORDER_TOTAL ? AmountBounds::MAX : AmountBounds::MIN;
            $appliedAmount = $effectiveBounds[$bound];

            if ((int) round((float) $value * 100) !== $appliedAmount) {
                $comment .= ' ' . __(
                    'This value is outside the Payplug limits: %1 applies instead.',
                    $this->amountBounds->formatAmount($appliedAmount, $currency)
                );
            }
        }

        $this->setData('comment', $comment);

        return parent::getHtml();
    }
}
