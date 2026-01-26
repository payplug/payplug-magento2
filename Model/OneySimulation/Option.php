<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Model\OneySimulation;

use Magento\Framework\DataObject;

class Option extends DataObject
{
    public const KEY_TYPE = 'type';
    public const KEY_COST = 'cost';
    public const KEY_RATE = 'rate';
    public const KEY_FIRST_DEPOSIT = 'first_deposit';
    public const KEY_TOTAL_AMOUNT = 'total_amount';
    public const KEY_SCHEDULES = 'schedules';

    /**
     * Get type
     *
     * @return string|null
     */
    public function getType()
    {
        return $this->_getData(self::KEY_TYPE);
    }

    /**
     * Set type
     *
     * @param string|null $type
     *
     * @return $this
     */
    public function setType($type): self
    {
        return $this->setData(self::KEY_TYPE, $type);
    }

    /**
     * Get cost
     *
     * @return float|null
     */
    public function getCost()
    {
        return $this->_getData(self::KEY_COST);
    }

    /**
     * Set cost
     *
     * @param float|null $cost
     *
     * @return $this
     */
    public function setCost($cost): self
    {
        return $this->setData(self::KEY_COST, $cost);
    }

    /**
     * Get rate
     *
     * @return float|null
     */
    public function getRate()
    {
        return $this->_getData(self::KEY_RATE);
    }

    /**
     * Set rate
     *
     * @param float|null $rate
     *
     * @return $this
     */
    public function setRate($rate): self
    {
        return $this->setData(self::KEY_RATE, $rate);
    }

    /**
     * Get first deposit
     *
     * @return float|null
     */
    public function getFirstDeposit()
    {
        return $this->_getData(self::KEY_FIRST_DEPOSIT);
    }

    /**
     * Set first deposit
     *
     * @param float|null $firstDeposit
     *
     * @return $this
     */
    public function setFirstDeposit($firstDeposit): self
    {
        return $this->setData(self::KEY_FIRST_DEPOSIT, $firstDeposit);
    }

    /**
     * Get total amount
     *
     * @return float|null
     */
    public function getTotalAmount()
    {
        return $this->_getData(self::KEY_TOTAL_AMOUNT);
    }

    /**
     * Set total amount
     *
     * @param float|null $totalAmount
     *
     * @return $this
     */
    public function setTotalAmount($totalAmount): self
    {
        return $this->setData(self::KEY_TOTAL_AMOUNT, $totalAmount);
    }

    /**
     * Get schedules
     *
     * @return null|array|Schedule[]
     */
    public function getSchedules()
    {
        return $this->_getData(self::KEY_SCHEDULES);
    }

    /**
     * Add schedule
     *
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
