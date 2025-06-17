<?php

namespace Payplug\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Payplug\Payments\Service\GetOauth2AccessToken;

class CleanOauthTokenDataOnChangeMode implements ObserverInterface
{
    public function __construct(
        private readonly GetOauth2AccessToken $getOauth2AccessToken
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        $websiteId = $observer->getEvent()->getData('website');
        $changedPaths = $observer->getEvent()->getData('changed_paths');

        $websiteId = $websiteId ? (int)$websiteId : null;

        // TODO no refresh if Oauth2 mode not active (check email ?)
        if (in_array('payplug_payments/general/environmentmode', $changedPaths)) {
            $this->getOauth2AccessToken->execute($websiteId, true);
        }
    }
}
