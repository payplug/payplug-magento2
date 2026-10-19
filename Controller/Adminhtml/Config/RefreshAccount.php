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
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Controller\Result\Redirect;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Logger\Logger as PayplugLogger;
use Payplug\Payments\Service\SynchronizeAccountData;

class RefreshAccount extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Payplug_Payments::general';

    /**
     * @param Context $context
     * @param Config $helper
     * @param SynchronizeAccountData $synchronizeAccountData
     * @param TypeListInterface $typeList
     * @param PayplugLogger $payplugLogger
     */
    public function __construct(
        Context $context,
        private readonly Config $helper,
        private readonly SynchronizeAccountData $synchronizeAccountData,
        private readonly TypeListInterface $typeList,
        private readonly PayplugLogger $payplugLogger
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
            $this->synchronizeAccountData->execute(
                (string) $this->helper->getConfigScope(),
                (int) $this->helper->getConfigScopeId()
            );
            $this->typeList->cleanType('config');
            $this->typeList->invalidate('full_page');

            $this->messageManager->addSuccessMessage(__('Your Payplug account information has been updated.'));
        } catch (Exception $e) {
            $this->payplugLogger->error('Could not refresh Payplug account information', ['exception' => $e]);
            $this->messageManager->addErrorMessage(
                __('Could not update your Payplug account information: %1', $e->getMessage())
            );
        }

        return $resultRedirect->setPath('adminhtml/system_config/edit', $params);
    }
}
