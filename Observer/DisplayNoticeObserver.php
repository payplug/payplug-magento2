<?php

namespace Payplug\Payments\Observer;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Message\ManagerInterface;

class DisplayNoticeObserver implements ObserverInterface
{
    /**
     * @param Http $request
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        private readonly Http $request,
        private readonly ManagerInterface $messageManager
    ) {
    }

    /**
     * Display notice message
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        // Get default website ID
        $params = $this->request->getParams();
        $is_website = !empty($params['website']);

        if (isset($this->request->getParams()['section']) && $is_website) {
            $currentRoute = $this->request->getParams()['section'];
            if ($currentRoute == 'payplug_payments') {
                $message = "Information : for specific payments methods please note that Payplug recommends to unable ";
                $message .= "‘Use Default’ configuration as permissions to these payments methods are different for ";
                $message .= "eachPayplug account.";

                $this->messageManager->addNoticeMessage(__($message));
            }
        }
    }
}
