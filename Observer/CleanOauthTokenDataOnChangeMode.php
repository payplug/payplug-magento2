<?php

declare(strict_types=1);

namespace Payplug\Payments\Observer;

use Laminas\Stdlib\Parameters;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Payplug\Payments\Service\GetOauth2AccessToken;
use Payplug\Payments\Helper\Config;

class CleanOauthTokenDataOnChangeMode implements ObserverInterface
{
    public function __construct(
        private readonly GetOauth2AccessToken $getOauth2AccessToken,
        private readonly Http $request,
        private readonly Config $config
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

        $postParams = $this->request->getPost();

        if ($postParams instanceof Parameters) {
            $postParams = $postParams->toArray();
        }

        if (!isset($postParams['config_state'])) {
            return;
        }

        if (in_array(Config::CONFIG_PATH . Config::OAUTH_ENVIRONMENT_MODE, $changedPaths)
            && !$this->config->isUsingLegacyConnexion($postParams)) {
            $this->getOauth2AccessToken->execute($websiteId, true);
        }
    }
}
