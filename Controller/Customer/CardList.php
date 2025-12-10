<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\Customer;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class CardList extends Action
{
    /**
     * @param Context $context
     * @param Session $customerSession
     * @param PageFactory $pageFactory
     */
    public function __construct(
        Context $context,
        private Session $customerSession,
        private PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Check customer authentication
     *
     * @param RequestInterface $request
     *
     * @return ResponseInterface|Page
     */
    public function dispatch(RequestInterface $request)
    {
        if (!$this->customerSession->authenticate()) {
            $this->_actionFlag->set('', 'no-dispatch', true);
        }

        return parent::dispatch($request);
    }

    /**
     * Display customer cards
     */
    public function execute(): Page
    {
        $title = __('My Saved Cards');
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set($title);

        $pageMainTitle = $page->getLayout()->getBlock('page.main.title');
        if ($pageMainTitle) {
            $pageMainTitle->setPageTitle($title);
        }

        return $page;
    }
}
