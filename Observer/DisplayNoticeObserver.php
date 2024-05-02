<?php

namespace Payplug\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\WebsiteFactory;

use Payplug\Payments\Helper\Config;

class DisplayNoticeObserver implements ObserverInterface
{
    protected $request;

    protected $_messageManager;


    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;


    /**
     * @var WebsiteFactory
     */
    protected $websiteFactory;

    public function __construct(
        \Magento\Framework\App\Request\Http $request,
        ManagerInterface $messageManager,
        StoreManagerInterface $storeManager,
        WebsiteFactory $websiteFactory
    ) {
        $this->request = $request;
        $this->_messageManager = $messageManager;
        $this->storeManager = $storeManager;
        $this->websiteFactory = $websiteFactory;
    }

    public function execute(Observer $observer)
    {
        // Get default website ID
        $params = $this->request->getParams();
        $is_website = !empty($params['website']) ? true : false;

        if (isset($this->request->getParams()['section']) && $is_website) {
            $currentRoute = $this->request->getParams()['section'];
            if ($currentRoute == 'payplug_payments') {
                $this->_messageManager->addNoticeMessage(__("Information : for specific payments methods please note that Payplug recommends to unable ‘Use Default’ configuration as permissions to these payments methods are different for each Payplug account."));
            }
        }


    }
}
