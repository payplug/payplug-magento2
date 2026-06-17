<?php

declare(strict_types=1);

namespace Payplug\Payments\Helper;

use Payplug\Core\APIRoutes;

class ApiUrlInitializer
{
    /**
     * Override API and service base URLs from environment variables.
     * PAYPLUG_API_BASE_URL and PAYPLUG_SERVICE_BASE_URL allow pointing to staging/internal environments.
     * When unset, the lib defaults (https://api.payplug.com / https://retail.service.payplug.com) are used.
     *
     * @return void
     */
    public function init(): void
    {
        $apiBaseUrl = getenv('PAYPLUG_API_BASE_URL');
        $serviceBaseUrl = getenv('PAYPLUG_SERVICE_BASE_URL');

        if ($apiBaseUrl) {
            APIRoutes::setApiBaseUrl($apiBaseUrl);
        }
        if ($serviceBaseUrl) {
            APIRoutes::setServiceBaseUrl($serviceBaseUrl);
        }
    }
}
