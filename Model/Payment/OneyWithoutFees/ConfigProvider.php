<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Model\Payment\OneyWithoutFees;

use Payplug\Payments\Gateway\Config\OneyWithoutFees;
use Payplug\Payments\Model\Payment\AbstractOneyConfigProvider;

class ConfigProvider extends AbstractOneyConfigProvider
{
    /**
     * @inheritdoc
     */
    protected function getMethodCode(): string
    {
        return OneyWithoutFees::METHOD_CODE;
    }

    /**
     * @inheritdoc
     */
    protected function getLogos(): array
    {
        if ($this->isItalian()) {
            return [
                'logo' => 'Payplug_Payments::images/oney_without_fees/3x4x-it.svg',
                'logo_ko' => 'Payplug_Payments::images/oney_without_fees/3x4x-alt-it.svg',
            ];
        }

        return [
            'logo' => 'Payplug_Payments::images/oney_without_fees/3x4x.svg',
            'logo_ko' => 'Payplug_Payments::images/oney_without_fees/3x4x-alt.svg',
        ];
    }

    /**
     * @inheritdoc
     */
    protected function getMoreInfoUrl(): string
    {
        return $this->oneyHelper->getMoreInfoUrlWithoutFees();
    }
}
