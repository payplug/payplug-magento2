<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\Adminhtml\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator;
use Payplug\Payments\Helper\Config;

class Logout extends Action
{
    public function __construct(
        Context $context,
        private Config $helper,
        private Validator $formKeyValidator,
        private RequestInterface $request,
    ) {
        parent::__construct($context);
    }

    /**
     * Logout PayPlug account
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        $formKeyValidation = $this->formKeyValidator->validate($this->request);
        if (!$formKeyValidation) {
            $this->messageManager->addErrorMessage(
                __('Your session has expired')
            );

            return $resultRedirect->setPath('*/*/');
        }

        $this->helper->initScopeData();
        $this->helper->clearConfig();

        $params = [
            '_secure' => true,
            'section' => 'payplug_payments',
        ];

        if ($website = $this->_request->getParam('website')) {
            $params['website'] = $website;
        }

        if ($store = $this->_request->getParam('store')) {
            $params['store'] = $store;
        }

        return $resultRedirect->setPath('adminhtml/system_config/edit', $params);
    }
}
