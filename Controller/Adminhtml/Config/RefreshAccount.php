<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Controller\Adminhtml\Config;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory as ConfigDataCollectionFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Service\SynchronizeAccountData;

class RefreshAccount extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Payplug_Payments::general';

    /**
     * @param Context $context
     * @param Config $helper
     * @param SynchronizeAccountData $synchronizeAccountData
     * @param TypeListInterface $typeList
     * @param Logger $logger
     * @param ConfigDataCollectionFactory $configDataCollectionFactory
     */
    public function __construct(
        Context $context,
        private readonly Config $helper,
        private readonly SynchronizeAccountData $synchronizeAccountData,
        private readonly TypeListInterface $typeList,
        private readonly Logger $logger,
        private readonly ConfigDataCollectionFactory $configDataCollectionFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Refresh Payplug account information in the scope holding the Payplug credentials
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $params = [
            '_secure' => true,
            'section' => 'payplug_payments',
        ];

        if ($website = $this->getRequest()->getParam('website')) {
            $params['website'] = $website;
        }

        if ($this->getRequest()->getParam('store')) {
            return $resultRedirect->setPath('adminhtml/system_config/edit', $params);
        }

        $this->helper->initScopeData();

        try {
            [$scope, $scopeId] = $this->getCredentialsScope();
            $environmentMode = $this->helper->getConfigValue('environmentmode', $scope, $scopeId);
            $apiKey = $this->helper->getApiKey($environmentMode === Config::ENVIRONMENT_TEST, $scopeId, $scope);

            if (empty($apiKey)) {
                throw new LocalizedException(__(
                    'We are not able to retrieve your account information. ' .
                    'Please go to section Sales > Payplug Payments to log in again.'
                ));
            }

            $this->synchronizeAccountData->execute($apiKey, $scope, $scopeId);
            $this->typeList->cleanType('config');
            $this->typeList->invalidate('full_page');

            $this->messageManager->addSuccessMessage(__('Your Payplug account information has been updated.'));
        } catch (Exception $e) {
            $this->logger->error('Could not refresh Payplug account information', ['exception' => $e]);
            $this->messageManager->addErrorMessage(
                __('Could not update your Payplug account information: %1', $e->getMessage())
            );
        }

        return $resultRedirect->setPath('adminhtml/system_config/edit', $params);
    }

    /**
     * Get the scope holding the Payplug credentials: the current website when set there, else the default scope
     *
     * @return array
     */
    private function getCredentialsScope(): array
    {
        $websiteId = (int) $this->getRequest()->getParam('website');

        if ($websiteId && $this->hasWebsiteCredentials($websiteId)) {
            return [ScopeInterface::SCOPE_WEBSITES, $websiteId];
        }

        return [ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0];
    }

    /**
     * Are Payplug credentials (legacy or OAuth2) set on the website itself, not inherited from default scope
     *
     * @param int $websiteId
     * @return bool
     */
    private function hasWebsiteCredentials(int $websiteId): bool
    {
        $collection = $this->configDataCollectionFactory->create();
        $collection->addFieldToFilter('path', ['in' => [
                Config::CONFIG_PATH . 'email',
                Config::OAUTH_CONFIG_PATH . Config::OAUTH_EMAIL,
            ]])
            ->addFieldToFilter('scope', ScopeInterface::SCOPE_WEBSITES)
            ->addFieldToFilter('scope_id', $websiteId)
            ->addFieldToFilter('value', ['neq' => '']);

        return $collection->getSize() > 0;
    }
}
