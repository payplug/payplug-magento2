<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Observer;

use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Service\SynchronizeAccountData;

class SynchronizeAccountDataOnChangeMode implements ObserverInterface
{
    /**
     * @param SynchronizeAccountData $synchronizeAccountData
     * @param ReinitableConfigInterface $appConfig
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        private readonly SynchronizeAccountData $synchronizeAccountData,
        private readonly ReinitableConfigInterface $appConfig,
        private readonly ManagerInterface $messageManager
    ) {
    }

    /**
     * Synchronize the Payplug account data once the new environment mode is saved
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $changedPaths = (array) $observer->getEvent()->getData('changed_paths');

        if (!in_array(Config::CONFIG_PATH . Config::OAUTH_ENVIRONMENT_MODE, $changedPaths, true)) {
            return;
        }

        $websiteId = (int) $observer->getEvent()->getData('website');

        try {
            $this->synchronizeAccountData->execute(
                $websiteId ? ScopeInterface::SCOPE_WEBSITES : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                $websiteId
            );
            $this->appConfig->reinit();
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
    }
}
