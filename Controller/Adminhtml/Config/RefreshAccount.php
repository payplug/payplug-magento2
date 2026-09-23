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
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Service\SynchronizeAccountData;

class RefreshAccount extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Payplug_Payments::general';

    /**
     * @param Context $context
     * @param Config $helper
     * @param SynchronizeAccountData $synchronizeAccountData
     * @param TypeListInterface $typeList
     * @param Logger $logger
     */
    public function __construct(
        Context $context,
        private readonly Config $helper,
        private readonly SynchronizeAccountData $synchronizeAccountData,
        private readonly TypeListInterface $typeList,
        private readonly Logger $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Refresh Payplug account information in the current configuration scope
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        $this->helper->initScopeData();

        try {
            $apiKey = $this->helper->getApiKey($this->helper->getIsSandbox());

            if (empty($apiKey)) {
                throw new LocalizedException(__(
                    'We are not able to retrieve your account information. ' .
                    'Please go to section Sales > Payplug Payments to log in again.'
                ));
            }

            $this->synchronizeAccountData->execute($apiKey);
            $this->typeList->cleanType('config');
            $this->typeList->invalidate('full_page');

            $this->messageManager->addSuccessMessage(__('Your Payplug account information has been updated.'));
        } catch (Exception $e) {
            $this->logger->error('Could not refresh Payplug account information', ['exception' => $e]);
            $this->messageManager->addErrorMessage(
                __('Could not update your Payplug account information: %1', $e->getMessage())
            );
        }

        $params = [
            '_secure' => true,
            'section' => 'payplug_payments',
        ];

        if ($website = $this->getRequest()->getParam('website')) {
            $params['website'] = $website;
        }

        if ($store = $this->getRequest()->getParam('store')) {
            $params['store'] = $store;
        }

        return $resultRedirect->setPath('adminhtml/system_config/edit', $params);
    }
}
