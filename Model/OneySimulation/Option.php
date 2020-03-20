<?php

namespace Payplug\Payments\Model\OneySimulation;

use Magento\Framework\DataObject;

class Option extends DataObject
{
    const KEY_TYPE = 'type';
    const KEY_COST = 'cost';
    const KEY_RATE = 'rate';
    const KEY_FIRST_DEPOSIT = 'first_deposit';
    const KEY_TOTAL_AMOUNT = 'total_amount';
    const KEY_SCHEDULES = 'schedules';

    /**
     * @return string|null
     */
    public function getType()
    {
        return $this->_getData(self::KEY_TYPE);
    }

    /**
     * @param string|null $type
     *
     * @return $this
     */
    public function setType($type): self
    {
        return $this->setData(self::KEY_TYPE, $type);
    }

    /**
     * @return float|null
     */
    public function getCost()
    {
        return $this->_getData(self::KEY_COST);
    }

    /**
     * @param float|null $cost
     *
     * @return $this
     */
    public function setCost($cost): self
    {
        return $this->setData(self::KEY_COST, $cost);
    }

    /**
     * @return float|null
     */
    public function getRate()
    {
        return $this->_getData(self::KEY_RATE);
    }

    /**
     * @param float|null $rate
     *
     * @return $this
     */
    public function setRate($rate): self
    {
        return $this->setData(self::KEY_RATE, $rate);
    }

    /**
     * @return float|null
     */
    public function getFirstDeposit()
    {
        return $this->_getData(self::KEY_FIRST_DEPOSIT);
    }

    /**
     * @param float|null $firstDeposit
     *
     * @return $this
     */
    public function setFirstDeposit($firstDeposit): self
    {
        return $this->setData(self::KEY_FIRST_DEPOSIT, $firstDeposit);
    }

    /**
     * @return float|null
     */
    public function getTotalAmount()
    {
        return $this->_getData(self::KEY_TOTAL_AMOUNT);
    }

    /**
     * @param float|null $cost
     *
     * @return $this
     */
    public function setTotalAmount($totalAmount): self
    {
        return $this->setData(self::KEY_TOTAL_AMOUNT, $totalAmount);
    }

    /**
     * @return null|array|Schedule[]
     */
    public function getSchedules()
    {
        return $this->_getData(self::KEY_SCHEDULES);
    }

    /**
     * @param Schedule $schedule
     *
     * @return $this
     */
    public function addSchedule(Schedule $schedule): self
    {
        $schedules = $this->_getData(self::KEY_SCHEDULES);
        if ($schedules === null) {
            $schedules = [];
        }
        $schedules[] = $schedule;

        return $this->setData(self::KEY_SCHEDULES, $schedules);
    }
}
