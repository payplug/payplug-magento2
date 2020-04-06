<?php

namespace Payplug\Payments\Model\OneySimulation;

use Magento\Framework\DataObject;

class Schedule extends DataObject
{
    const KEY_AMOUNT = 'amount';
    const KEY_DATE = 'date';

    /**
     * @return float|null
     */
    public function getAmount()
    {
        return $this->_getData(self::KEY_AMOUNT);
    }

    /**
     * @param float|null $amount
     *
     * @return $this
     */
    public function setAmount($amount): self
    {
        return $this->setData(self::KEY_AMOUNT, $amount);
    }

    /**
     * @return \DateTime|null
     */
    public function getDate()
    {
        return $this->_getData(self::KEY_DATE);
    }

    /**
     * @param \DateTime|null $date
     *
     * @return $this
     */
    public function setDate($date): self
    {
        return $this->setData(self::KEY_DATE, $date);
    }
}
