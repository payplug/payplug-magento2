<?php

namespace Payplug\Payments\Model\OneySimulation;

use Magento\Framework\DataObject;

class Schedule extends DataObject
{
    public const KEY_AMOUNT = 'amount';
    public const KEY_DATE = 'date';

    /**
     * Get amount
     *
     * @return float|null
     */
    public function getAmount()
    {
        return $this->_getData(self::KEY_AMOUNT);
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
        return $this->setData(self::KEY_AMOUNT, $amount);
    }

    /**
     * Get date
     *
     * @return \DateTime|null
     */
    public function getDate()
    {
        return $this->_getData(self::KEY_DATE);
    }

    /**
     * Set date
     *
     * @param \DateTime|null $date
     *
     * @return $this
     */
    public function setDate($date): self
    {
        return $this->setData(self::KEY_DATE, $date);
    }
}
