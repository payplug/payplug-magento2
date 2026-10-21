<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Payment\Model\Checks\TotalMinMax;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Model\Payment\AmountBounds;

class OrderTotal extends Value
{
    /**
     * @param AmountBounds $amountBounds
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        private readonly AmountBounds $amountBounds,
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Save a zero limit as empty (no limit), then validate a changed limit: a positive number, within the Payplug
     * limits and consistent with the other limit
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = trim((string) $this->getValue());

        if (is_numeric($value) && (float) $value === 0.0) {
            $value = '';
        }

        $this->setValue($value);

        if ($value === '' || !$this->isValueChanged()) {
            return parent::beforeSave();
        }

        if (!is_numeric($value) || (float) $value < 0) {
            throw new LocalizedException(
                __('%1: "%2" must be a positive number.', $this->resolveMethodTitle(), $this->resolveFieldLabel())
            );
        }

        $this->validateAccountBounds((float) $value);
        $this->validateMinLowerThanMax();

        return parent::beforeSave();
    }

    /**
     * Check that the value is within the limits set by Payplug for the saved scope
     *
     * @param float $value
     * @return void
     * @throws LocalizedException
     */
    private function validateAccountBounds(float $value): void
    {
        $methodCode = $this->resolveMethodCode();
        $scopeType = $this->resolveScopeType();
        $scopeId = (int) $this->getScopeId();
        $currency = $this->amountBounds->getBaseCurrency($scopeType, $scopeId);
        $accountBounds = $this->amountBounds->getAccountBounds($methodCode, $currency, $scopeType, $scopeId);

        if ($accountBounds === null) {
            return;
        }

        $amount = (int) round($value * 100);

        if ($amount < $accountBounds[AmountBounds::MIN] || $amount > $accountBounds[AmountBounds::MAX]) {
            throw new LocalizedException(
                __(
                    '%1: "%2" must be between %3 and %4, the limits set by Payplug.',
                    $this->resolveMethodTitle(),
                    $this->resolveFieldLabel(),
                    $this->amountBounds->formatAmount($accountBounds[AmountBounds::MIN], $currency),
                    $this->amountBounds->formatAmount($accountBounds[AmountBounds::MAX], $currency)
                )
            );
        }
    }

    /**
     * Check that the minimum order total is lower than or equal to the maximum order total
     *
     * @return void
     * @throws LocalizedException
     */
    private function validateMinLowerThanMax(): void
    {
        $isMin = $this->resolveFieldId() === TotalMinMax::MIN_ORDER_TOTAL;
        $sibling = $isMin ? TotalMinMax::MAX_ORDER_TOTAL : TotalMinMax::MIN_ORDER_TOTAL;
        $siblingValue = trim(
            (string) ($this->getFieldsetDataValue($sibling) ?? $this->resolveSiblingConfigValue($sibling))
        );

        if (!is_numeric($siblingValue) || (float) $siblingValue <= 0) {
            return;
        }

        $value = (float) $this->getValue();
        [$min, $max] = $isMin ? [$value, (float) $siblingValue] : [(float) $siblingValue, $value];

        if ($min > $max) {
            throw new LocalizedException(
                __(
                    '%1: "%2" must be lower than or equal to "%3".',
                    $this->resolveMethodTitle(),
                    __('Minimum Order Total'),
                    __('Maximum Order Total')
                )
            );
        }
    }

    /**
     * Get the configured value of the other limit, from the default scope when it is saved as inherited
     *
     * @param string $sibling
     * @return mixed
     */
    private function resolveSiblingConfigValue(string $sibling): mixed
    {
        $scopeType = $this->resolveScopeType();
        $scopeId = (int) $this->getScopeId();
        $groups = (array) $this->getData('groups');

        if ($scopeType === ScopeInterface::SCOPE_WEBSITES
            && !empty($groups[$this->getData('group_id')]['fields'][$sibling]['inherit'])
        ) {
            $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
            $scopeId = 0;
        }

        return $this->_config->getValue(
            sprintf('payment/%s/%s', $this->resolveMethodCode(), $sibling),
            $scopeType,
            $scopeId
        );
    }

    /**
     * Get the scope type of the saved value
     *
     * @return string
     */
    private function resolveScopeType(): string
    {
        return (string) ($this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
    }

    /**
     * Get the payment method code from the path payment/<method_code>/<field>
     *
     * @return string
     */
    private function resolveMethodCode(): string
    {
        return (string) (explode('/', (string) $this->getPath())[1] ?? '');
    }

    /**
     * Get the field id from the path payment/<method_code>/<field>
     *
     * @return string
     */
    private function resolveFieldId(): string
    {
        return (string) (explode('/', (string) $this->getPath())[2] ?? '');
    }

    /**
     * Get the payment method title of the saved scope
     *
     * @return string
     */
    private function resolveMethodTitle(): string
    {
        $methodCode = $this->resolveMethodCode();
        $title = (string) $this->_config->getValue(
            sprintf('payment/%s/title', $methodCode),
            $this->resolveScopeType(),
            (int) $this->getScopeId()
        );

        return $title !== '' ? $title : $methodCode;
    }

    /**
     * Get the translated field label
     *
     * @return string
     */
    private function resolveFieldLabel(): string
    {
        return (string) __($this->getFieldConfig()['label'] ?? $this->resolveFieldId());
    }
}
