<?php
declare(strict_types=1);

namespace Payplug\Payments\Controller\Adminhtml\Config;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;

class Oauth2Return implements HttpGetActionInterface
{
    public function __construct(
        private readonly RedirectFactory $redirectFactory,
        private readonly MessageManagerInterface $messageManager
    ) {
    }

    public function execute(): Redirect
    {
        $this->messageManager->addSuccessMessage(__('Success'));

        return $this->redirectFactory->create()->setPath('adminhtml/system_config/edit', ['section' => 'payplug_payments']);
    }
}
