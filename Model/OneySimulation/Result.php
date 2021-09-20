<?php

namespace Payplug\Payments\Model\OneySimulation;

use Magento\Framework\DataObject;

class Result extends DataObject
{
    const KEY_SUCCESS = 'success';
    const KEY_MESSAGE = 'message';
    const KEY_OPTIONS = 'options';
    const KEY_AMOUNT = 'amount';
    const KEY_METHOD = 'method';

    /**
     * @return bool|null
     */
    public function getSuccess()
    {
        return $this->_getData(self::KEY_SUCCESS);
    }

    /**
     * @param bool|null $success
     *
     * @return $this
     */
    public function setSuccess($success): self
    {
        return $this->setData(self::KEY_SUCCESS, $success);
    }

    /**
     * @return null|string
     */
    public function getMessage()
    {
        return $this->_getData(self::KEY_MESSAGE);
    }

    /**
     * @param string|null $message
     *
     * @return $this
     */
    public function setMessage($message): self
    {
        return $this->setData(self::KEY_MESSAGE, $message);
    }

    /**
     * @return null|array|Option[]
     */
    public function getOptions()
    {
        return $this->_getData(self::KEY_OPTIONS);
    }

    /**
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
     * @return float|null
     */
    public function getAmount()
    {
        return $this->_getData(self::KEY_AMOUNT);
    }

    /**
     * @param float|null $message
     *
     * @return $this
     */
    public function setAmount($amount): self
    {
        return $this->setData(self::KEY_AMOUNT, $amount);
    }

    /**
     * @return string|null
     */
    public function getMethod()
    {
        return $this->_getData(self::KEY_METHOD);
    }

    /**
     * @param string|null $method
     *
     * @return $this
     */
    public function setMethod($method): self
    {
        return $this->setData(self::KEY_METHOD, $method);
    }
}
