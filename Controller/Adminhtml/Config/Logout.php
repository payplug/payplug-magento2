<?php

namespace Payplug\Payments\Controller\Adminhtml\Config;

use Payplug\Payments\Helper\Data;

class Logout extends \Magento\Backend\App\Action
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param Data                                $helper
     */
    public function __construct(\Magento\Backend\App\Action\Context $context, Data $helper)
    {
        $this->helper = $helper;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        $this->helper->initScopeData();
        $this->helper->clearConfig();

        $params = [
            '_secure' => true,
            'section' => 'payment',
        ];

        if ($website = $this->_request->getParam('website')) {
            $params['website'] = $website;
        }

        if ($store = $this->_request->getParam('store')) {
            $params['store'] = $store;
        }

        return $this->_redirect('adminhtml/system_config/edit', $params);
    }
}
