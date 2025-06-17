<?php

namespace Payplug\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Payplug\Payments\Service\RenewOauth2AccessToken;

class CleanOauthTokenDataOnChangeMode implements ObserverInterface
{
    public function __construct(
        private readonly RenewOauth2AccessToken $renewOauth2AccessToken
    ) {
    }

    public function execute(Observer $observer): void
    {
        $websiteId = $observer->getEvent()->getData('website');
        $changedPaths = $observer->getEvent()->getData('changed_paths');

        $websiteId = $websiteId ? (int)$websiteId : null;

        if (in_array('payplug_payments/general/environmentmode', $changedPaths)) {
            $this->renewOauth2AccessToken->execute($websiteId, true);
        }
    }
}
