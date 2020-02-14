<?php

namespace Payplug\Payments\Model\OneySimulation;

use Magento\Framework\DataObject;

class Result extends DataObject
{
    const KEY_SUCCESS = 'success';
    const KEY_MESSAGE = 'message';
    const KEY_OPTIONS = 'options';

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
}
