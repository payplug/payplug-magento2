<?php

namespace Payplug\Payments\Helper\Http;

class StandardClient extends AbstractClient
{
    /**
     * @inheritdoc
     */
    protected function prepareReturnData($payplugObject, $data)
    {
        return ['payment' => $payplugObject];
    }

    /**
     * @inheritdoc
     */
    protected function createPayplugObject($payplugData)
    {
        return \Payplug\Payment::create($payplugData);
    }
}
