<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Logger\Logger as PayplugLogger;
use Payplug\Payments\Service\GetOauth2AccessTokenData;
use Payplug\Payments\Service\GetOauth2CompanyNameFromApi;
use Payplug\Payments\Service\GetOauth2CompanyNameFromConfig;
use Throwable;

class SyncOauth2CompanyNameObserver implements ObserverInterface
{
    private const SECTION = 'payplug_payments';
    private const EMAIL_PATH = Config::OAUTH_CONFIG_PATH . Config::OAUTH_EMAIL;
    private const COMPANY_NAME_PATH = Config::OAUTH_CONFIG_PATH . Config::OAUTH_COMPANY_NAME;

    /**
     * @param RequestInterface $request
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $configWriter
     * @param GetOauth2CompanyNameFromConfig $getOauth2CompanyNameFromConfig
     * @param GetOauth2CompanyNameFromApi $getOauth2CompanyNameFromApi
     * @param GetOauth2AccessTokenData $getOauth2AccessTokenData
     * @param ManagerInterface $messageManager
     * @param PayplugLogger $payplugLogger
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WriterInterface $configWriter,
        private readonly GetOauth2CompanyNameFromConfig $getOauth2CompanyNameFromConfig,
        private readonly GetOauth2CompanyNameFromApi $getOauth2CompanyNameFromApi,
        private readonly GetOauth2AccessTokenData $getOauth2AccessTokenData,
        private readonly ManagerInterface $messageManager,
        private readonly PayplugLogger $payplugLogger
    ) {
    }

    /**
     * Retrieve and store the Oauth2 company name when it is missing for the displayed scope
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        if ($this->request->getParam('section') !== self::SECTION) {
            return;
        }

        $websiteId = (int)$this->request->getParam('website') ?: null;
        $emailWebsiteId = $this->getOauth2CompanyNameFromConfig->getEmailWebsiteId($websiteId);

        if ($emailWebsiteId === null && !$this->scopeConfig->getValue(self::EMAIL_PATH)) {
            return;
        }

        if ((string)$this->getOauth2CompanyNameFromConfig->execute($emailWebsiteId) !== '') {
            return;
        }

        try {
            $accessTokenData = $this->getOauth2AccessTokenData->execute($emailWebsiteId);
            $companyName = $this->getOauth2CompanyNameFromApi->execute(
                (string)($accessTokenData['access_token'] ?? '')
            );
        } catch (Throwable $e) {
            $this->payplugLogger->warning(sprintf('Could not retrieve the Oauth2 access token: %s', $e->getMessage()));
            $companyName = '';
        }

        if ($companyName === '') {
            $this->messageManager->addWarningMessage(
                __('The company name of your Payplug account could not be retrieved.')
            );

            return;
        }

        $this->configWriter->save(
            self::COMPANY_NAME_PATH,
            $companyName,
            $emailWebsiteId ? ScopeInterface::SCOPE_WEBSITES : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $emailWebsiteId ?: 0
        );

        $this->messageManager->addSuccessMessage(
            __('The company name of your Payplug account has been synchronized: %1', $companyName)
        );
    }
}
