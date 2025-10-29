<?php

declare(strict_types=1);

namespace Payplug\Payments\Plugin;

use Magento\Framework\App\RequestInterface as Request;
use Magento\Webapi\Controller\Rest;
use Payplug\Payments\Service\InitEnvQa;

class InitEnvQaOnRestDispatch
{
    public function __construct(
        private readonly InitEnvQa $initEnvQa
    ) {
    }

    public function beforeDispatch(Rest $subject, Request $request): array
    {
        $this->initEnvQa->execute();

        return [$request];
    }
}
