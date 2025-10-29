<?php

declare(strict_types=1);

namespace Payplug\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Payplug\Payments\Service\InitEnvQa;

class InitEnvQaOnFrontDispatch implements ObserverInterface
{
    public function __construct(
        private readonly InitEnvQa $initEnvQa
    ) {
    }

    public function execute(Observer $observer): void
    {
        $this->initEnvQa->execute();
    }
}
