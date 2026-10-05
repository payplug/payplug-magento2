<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Model\Payment\Oney;

use Payplug\Payments\Gateway\Config\Oney;
use Payplug\Payments\Model\Payment\AbstractOneyConfigProvider;

class ConfigProvider extends AbstractOneyConfigProvider
{
    /**
     * @inheritdoc
     */
    protected function getMethodCode(): string
    {
        return Oney::METHOD_CODE;
    }

    /**
     * @inheritdoc
     */
    protected function getLogos(): array
    {
        return [
            'logo' => 'Payplug_Payments::images/logos/oney_3x_4x.svg',
            'logo_ko' => 'Payplug_Payments::images/logos/oney_3x_4x_alt.svg',
        ];
    }

    /**
     * @inheritdoc
     */
    protected function getMoreInfoUrl(): string
    {
        return $this->oneyHelper->getMoreInfoUrl();
    }
}
