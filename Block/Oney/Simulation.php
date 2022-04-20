<?php

namespace Payplug\Payments\Block\Oney;

use Magento\Framework\View\Element\Template;
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
}
