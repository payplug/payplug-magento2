<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Block\Oney;

use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Oney;
use Payplug\Payments\Model\OneySimulation\Result;

class Simulation extends Template
{
    /**
     * @var float
     */
    private $amount;

    /**
     * @var int
     */
    private $qty;

    /**
     * @var Oney
     */
    private $oneyHelper;

    /**
     * @param Template\Context $context
     * @param Oney             $oneyHelper
     * @param array            $data
     */
    public function __construct(
        Template\Context $context,
        Oney $oneyHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->oneyHelper = $oneyHelper;
    }

    /**
     * @inheritdoc
     */
    public function _toHtml(): string
    {
        if ($this->oneyHelper->canDisplayOney()) {
            return parent::_toHtml();
        }

        // Don't render template in case oney isn't available
        return '';
    }

    /**
     * Set amount
     *
     * @param float|null $amount
     *
     * @return $this
     */
    public function setAmount($amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * Get amount
     *
     * @return float|null
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Set quantity
     *
     * @param int|null $qty
     *
     * @return $this
     */
    public function setQty($qty): self
    {
        $this->qty = $qty;

        return $this;
    }

    /**
     * Get quantity
     *
     * @return int|null
     */
    public function getQty()
    {
        return $this->qty;
    }

    /**
     * Get Oney simulation
     *
     * @param bool $validationOnly
     *
     * @return Result
     */
    public function getOneySimulation(bool $validationOnly = false): Result
    {
        return $this->oneyHelper->getOneySimulation($this->getAmount(), null, $this->getQty(), $validationOnly);
    }

    /**
     * Get Oney min/max amounts
     *
     * @return array|bool
     */
    public function getOneyAmounts()
    {
        return $this->oneyHelper->getOneyAmounts();
    }

    /**
     * Check if current store is in italian
     *
     * @return bool
     */
    public function isItalianStore()
    {
        $localeCode = $this->_scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE);

        return $localeCode === 'it_IT';
    }

    /**
     * Check if merchand has an italian PayPlug account
     *
     * @return bool
     */
    public function isMerchandItalian()
    {
        return $this->oneyHelper->isMerchandItalian();
    }

    /**
     * Get more info url
     *
     * @return string
     */
    public function getMoreInfoUrl()
    {
        return $this->oneyHelper->getMoreInfoUrl();
    }

    /**
     * Get more info url
     *
     * @return string
     */
    public function getMoreInfoUrlWithoutFees()
    {
        return $this->oneyHelper->getMoreInfoUrlWithoutFees();
    }
}
