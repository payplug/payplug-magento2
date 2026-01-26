<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Controller\Customer;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\Page;
use Payplug\Payments\Helper\Card;

class CardDelete extends Action
{
    /**
     * @param Context $context
     * @param Card $helper
     * @param Session $customerSession
     * @param Validator $formKeyValidator
     * @param RequestInterface $request
     */
    public function __construct(
        Context $context,
        private Card $helper,
        private Session $customerSession,
        private Validator $formKeyValidator,
        private RequestInterface $request
    ) {
        parent::__construct($context);
    }

    /**
     * Check customer authentication
     *
     * @param RequestInterface $request
     *
     * @return ResponseInterface|Page|Redirect
     */
    public function dispatch(RequestInterface $request)
    {
        if (!$this->customerSession->authenticate()) {
            $this->_actionFlag->set('', 'no-dispatch', true);
        }

        return parent::dispatch($request);
    }

    /**
     * Delete customer card
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        $formKeyValidation = $this->formKeyValidator->validate($this->request);
        if (!$formKeyValidation) {
            $this->messageManager->addErrorMessage(
                __('Your session has expired')
            );

            return $resultRedirect->setPath('payplug_payments/customer/cardList');
        }

        try {
            $customerId = $this->customerSession->getCustomer()->getId();
            $customerCardId = $this->getRequest()->getParam('customer_card_id');
            $this->helper->deleteCustomerCard($customerId, $customerCardId);
            $this->messageManager->addSuccessMessage(__('Your card has been successfully deleted.'));
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('payplug_payments/customer/cardList');
    }
}
