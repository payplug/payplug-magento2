<?php

namespace Payplug\Payments\Observer;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config;

class PaymentConfigEditObserver implements ObserverInterface
{
    /**
     * @var Http
     */
    private $request;

    /**
     * @var Config
     */
    private $helper;

    /**
     * @param Http   $request
     * @param Config $helper
     */
    public function __construct(
        Http $request,
        Config $helper
    ) {
        $this->request = $request;
        $this->helper = $helper;
    }

    /**
     * Check Payplug integrated configuration
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer): void
    {
        $section = $this->request->getParam('section');
        if ($section !== 'payplug_payments') {
            return;
        }

        $this->helper->initScopeData();
        $websiteId = null;
        if ($this->helper->getConfigScope() === ScopeInterface::SCOPE_WEBSITES) {
            $websiteId = $this->helper->getConfigScopeId();
        }

        $this->helper->handleIntegratedPayment($websiteId, true);
    }
}
