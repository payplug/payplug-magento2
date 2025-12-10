<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\Adminhtml\Config;

use Laminas\Http\Response;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AdminAuthSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\UrlInterface;
use Payplug\Authentication as PayplugAuthentication;
use Payplug\Exception\ConfigurationException;
use Payplug\Payments\Logger\Logger;

class Oauth2FetchAuthCode extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Payplug_Payments::general';
    public const OAUTH_CONFIG_FETCH_DATA = 'payplug_payments_admin/config/oauth2FetchClientData';
    public const OAUTH_CONFIG_FETCH_AUTH = 'payplug_payments_admin/config/oauth2FetchAuthCode';

    /**
     * @param RequestInterface $request
     * @param UrlInterface $urlBuilder
     * @param RedirectFactory $redirectFactory
     * @param RawFactory $rawFactory
     * @param Logger $logger
     * @param AdminAuthSession $adminAuthSession
     * @param Context $context
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly UrlInterface $urlBuilder,
        private readonly RedirectFactory $redirectFactory,
        private readonly RawFactory $rawFactory,
        private readonly Logger $logger,
        private readonly AdminAuthSession $adminAuthSession,
        Context $context
    ) {
        parent::__construct($context);
    }

    /**
     * Fetch Auth Code from PayPlug Portal
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $clientId = $this->request->getParam('client_id');
        $companyId = $this->request->getParam('company_id');
        $websiteId = $this->request->getParam('website');
        $callbackUrl = $this->urlBuilder->getUrl(
            Oauth2FetchAuthCode::OAUTH_CONFIG_FETCH_DATA,
            ['website' => $websiteId]
        );

        $codeVerifier = bin2hex(openssl_random_pseudo_bytes(50));

        $this->adminAuthSession->setData(Oauth2FetchClientData::PAYPLUG_OAUTH2_AUTHENTICATION_CONTEXT_DATA, [
            'client_id' => $clientId,
            'company_id' => $companyId,
            'code_verifier' => $codeVerifier,
            'callback_url' => $callbackUrl,
        ]);

        try {
            PayplugAuthentication::initiateOAuth($clientId, $callbackUrl, $codeVerifier);

            $result = $this->rawFactory->create();
            $result->setHttpResponseCode(Response::STATUS_CODE_302);

            return $result;
        } catch (ConfigurationException $e) {
            $this->logger->error($e->getMessage());
            $this->messageManager->addErrorMessage(__('Could not retrieve Auth Code from Payplug Portal'));

            return $this->redirectFactory->create()->setPath(
                'adminhtml/system_config/edit',
                ['section' => 'payplug_payments']
            );
        }
    }
}
