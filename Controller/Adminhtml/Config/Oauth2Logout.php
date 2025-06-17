<?php
declare(strict_types=1);

namespace Payplug\Payments\Controller\Adminhtml\Config;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface as ConfigWriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Payplug\Payments\Helper\Config as ConfigHelper;

class Oauth2Logout implements HttpGetActionInterface
{
    public function __construct(
        private readonly RedirectFactory $redirectFactory,
        private readonly RequestInterface $request,
        private readonly MessageManagerInterface $messageManager,
        private readonly ConfigWriterInterface $configWriter,
        private readonly ConfigHelper $configHelper
    ) {
    }

    public function execute(): ResultInterface
    {
        $this->messageManager->addSuccessMessage(__('You have been logged out successfully'));

        $this->configHelper->initScopeData();
        $this->configHelper->clearConfig();

        return $this->redirectFactory->create()->setPath(
            'adminhtml/system_config/edit',
            ['section' => 'payplug_payments', 'website' => $this->getWebsiteId()]
        );
    }

    private function deleteConfig(string $path): void
    {
        $websiteId = $this->getWebsiteId();

        $this->configWriter->delete(
            $path,
            $websiteId ? StoreScopeInterface::SCOPE_WEBSITES : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $websiteId ?: 0
        );
    }

    private function getWebsiteId(): int
    {
        return (int)$this->request->getParam('website');
    }
}
