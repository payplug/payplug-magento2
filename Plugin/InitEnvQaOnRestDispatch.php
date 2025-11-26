<?php

declare(strict_types=1);

namespace Payplug\Payments\Plugin;

use Magento\Framework\App\RequestInterface as Request;
use Magento\Webapi\Controller\Rest;
use Payplug\Payments\Service\InitEnvQa;

class InitEnvQaOnRestDispatch
{
    private const TARGET_ROUTE_SEGMENTS = [
        'shipping-information',
        'payment-information'
    ];

    public function __construct(
        private readonly InitEnvQa $initEnvQa
    ) {
    }

    /**
     * @return null
     */
    public function beforeDispatch(Rest $subject, Request $request)
    {
        $path = $request->getPathInfo();
        $segments = explode('/', trim($path, '/'));
        $lastSegment = end($segments);

        if (in_array($lastSegment, self::TARGET_ROUTE_SEGMENTS)) {
            $this->initEnvQa->execute();
        }

        return null;
    }
}
