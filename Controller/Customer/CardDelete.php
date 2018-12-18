<?php

namespace Payplug\Payments\Controller\Customer;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Payplug\Payments\Helper\Card;

class CardDelete extends \Magento\Framework\App\Action\Action
{
    /**
     * @var Card
     */
    private $helper;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @param Context $context
     * @param Card    $helper
     * @param Session $customerSession
     */
    public function __construct(Context $context, Card $helper, Session $customerSession)
    {
        parent::__construct($context);
        $this->helper = $helper;
        $this->customerSession = $customerSession;
    }

    /**
     * Check customer authentication
     *
     * @param RequestInterface $request
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function dispatch(RequestInterface $request)
    {
        if (!$this->customerSession->authenticate()) {
            $this->_actionFlag->set('', 'no-dispatch', true);
        }

        return parent::dispatch($request);
    }

    public function execute()
    {
        try {
            $customerId = $this->customerSession->getCustomer()->getId();
            $customerCardId = $this->getRequest()->getParam('customer_card_id');
            $this->helper->deleteCustomerCard($customerId, $customerCardId);
            $this->messageManager->addSuccessMessage(__('Your card has been successfully deleted.'));
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->_redirect('payplug_payments/customer/cardList');
    }
}
