<?php

namespace Payplug\Payments\Controller\Adminhtml\Config;

use Payplug\Payments\Helper\Config;

class Logout extends \Magento\Backend\App\Action
{
    /**
     * @var Config
     */
    private $helper;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param Config                              $helper
     */
    public function __construct(\Magento\Backend\App\Action\Context $context, Config $helper)
    {
        $this->helper = $helper;
        parent::__construct($context);
    }

    /**
     * Logout PayPlug account
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
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

        $resultRedirect = $this->resultRedirectFactory->create();

        return $resultRedirect->setPath('adminhtml/system_config/edit', $params);
    }
}
