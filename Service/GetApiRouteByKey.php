<?php

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Framework\App\Filesystem\DirectoryList;
use Symfony\Component\Dotenv\DotenvFactory;
use Throwable;

class GetApiRouteByKey
{
    public function __construct(
        private readonly DotenvFactory $dotenvFactory,
        private readonly DirectoryList $directoryList
    ) {
    }

    public function execute(string $routeKey, string $fallbackValue): string
    {
        $rootPath = $this->directoryList->getRoot();
        $envPath = $rootPath . DIRECTORY_SEPARATOR . '.env.qa';

        $dotenv = $this->dotenvFactory->create();

        try {
            $dotenv->usePutenv()->load($envPath);
        } catch (Throwable) {
            return $fallbackValue;
        }

        return getenv($routeKey) ?: $fallbackValue;
    }
}
