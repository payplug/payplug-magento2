<?php

namespace Payplug\Payments\Helper\Http;

class OndemandClient extends StandardClient
{
    /**
     * @inheritdoc
     */
    protected function prepareReturnData($payplugObject, $data)
    {
        return array_merge(parent::prepareReturnData($payplugObject, $data), ['ondemandData' => $data['extra']]);
    }

    /**
     * @inheritdoc
     */
    protected function prepareData($data)
    {
        $data = parent::prepareData($data);
        unset($data['extra']);
        
        return $data;
    }
}
