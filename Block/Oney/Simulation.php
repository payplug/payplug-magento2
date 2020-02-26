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
     * @param float $amount
     *
     * @return $this
     */
    public function setAmount(float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * @return float|null
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @return Result
     */
    public function getOneySimulation(): Result
    {
        return $this->oneyHelper->getOneySimulation($this->getAmount());
    }

    /**
     * @return array|bool
     */
    public function getOneyAmounts()
    {
        return $this->oneyHelper->getOneyAmounts();
    }
}
