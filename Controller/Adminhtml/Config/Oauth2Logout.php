<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Controller\Adminhtml\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Payplug\Payments\Helper\Config as ConfigHelper;

class Oauth2Logout extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Payplug_Payments::general';

    /**
     * @param RedirectFactory $redirectFactory
     * @param RequestInterface $request
     * @param ConfigHelper $configHelper
     * @param Context $context
     */
    public function __construct(
        private readonly RedirectFactory $redirectFactory,
        private readonly RequestInterface $request,
        private readonly ConfigHelper $configHelper,
        Context $context
    ) {
        parent::__construct($context);
    }

    /**
     * Logout account
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $this->messageManager->addSuccessMessage(__('You have been logged out successfully'));

        $this->configHelper->initScopeData();
        $this->configHelper->clearConfig();

        return $this->redirectFactory->create()->setPath(
            'adminhtml/system_config/edit',
            ['section' => 'payplug_payments', 'website' => $this->getWebsiteId()]
        );
    }

    /**
     * Get website ID from request
     *
     * @return int
     */
    private function getWebsiteId(): int
    {
        return (int)$this->request->getParam('website');
    }
}
