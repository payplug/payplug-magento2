<?php
declare(strict_types=1);

namespace Payplug\Payments\Controller\Adminhtml\Config;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\UrlInterface;
use Payplug\Authentication as PayplugAuthentication;
use Payplug\Exception\ConfigurationException;

class Oauth2Auth implements HttpGetActionInterface
{
    public function __construct(
        private readonly RedirectFactory $redirectFactory,
        private readonly MessageManagerInterface $messageManager,
        private readonly PayplugAuthentication $payplugAuthentication,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function execute(): ResultInterface
    {
        $callbackUrl = $this->urlBuilder->getUrl('payplug_payments_admin/config/oauth2Return');

        try {
            $this->payplugAuthentication::initiateOAuth('', $callbackUrl, '');
            exit;
        } catch (ConfigurationException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->redirectFactory->create()->setPath(
                'adminhtml/system_config/edit',
                ['section' => 'payplug_payments']
            );
        }
    }
}
