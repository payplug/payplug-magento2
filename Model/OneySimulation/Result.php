<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Model\OneySimulation;

use Magento\Framework\DataObject;

class Result extends DataObject
{
    public const KEY_SUCCESS = 'success';
    public const KEY_MESSAGE = 'message';
    public const KEY_OPTIONS = 'options';
    public const KEY_AMOUNT = 'amount';
    public const KEY_METHOD = 'method';

    /**
     * Get success
     *
     * @return bool|null
     */
    public function getSuccess()
    {
        return $this->_getData(self::KEY_SUCCESS);
    }

    /**
     * Set success
     *
     * @param bool|null $success
     *
     * @return $this
     */
    public function setSuccess($success): self
    {
        return $this->setData(self::KEY_SUCCESS, $success);
    }

    /**
     * Get message
     *
     * @return null|string
     */
    public function getMessage()
    {
        return $this->_getData(self::KEY_MESSAGE);
    }

    /**
     * Set message
     *
     * @param string|null $message
     *
     * @return $this
     */
    public function setMessage($message): self
    {
        return $this->setData(self::KEY_MESSAGE, $message);
    }

    /**
     * Get options
     *
     * @return null|array|Option[]
     */
    public function getOptions()
    {
        return $this->_getData(self::KEY_OPTIONS);
    }

    /**
     * Add option
     *
     * @param Option $option
     *
     * @return $this
     */
    public function addOption(Option $option): self
    {
        $options = $this->_getData(self::KEY_OPTIONS);
        if ($options === null) {
            $options = [];
        }
        $options[] = $option;

        return $this->setData(self::KEY_OPTIONS, $options);
    }

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
     * Get method
     *
     * @return string|null
     */
    public function getMethod()
    {
        return $this->_getData(self::KEY_METHOD);
    }

    /**
     * Set method
     *
     * @param string|null $method
     *
     * @return $this
     */
    public function setMethod($method): self
    {
        return $this->setData(self::KEY_METHOD, $method);
    }
}
