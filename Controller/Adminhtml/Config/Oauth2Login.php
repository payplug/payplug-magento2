<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\Adminhtml\Config;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\UrlInterface;
use Payplug\Authentication as PayplugAuthentication;

class Oauth2Login implements HttpGetActionInterface
{
    public function __construct(
        private readonly RedirectFactory $redirectFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly RequestInterface $request
    ) {
    }

    public function execute(): ResultInterface
    {
        $websiteId = $this->request->getParam('website');
        $callbackUrl = $this->urlBuilder->getUrl(
            Oauth2FetchAuthCode::OAUTH_CONFIG_FETCH_AUTH,
            ['website' => $websiteId]
        );
        $oauthCallbackUrl = $this->urlBuilder->getUrl(
            Oauth2FetchAuthCode::OAUTH_CONFIG_FETCH_DATA,
            ['website' => $websiteId]
        );

        /** @var string $url */
        $url = PayplugAuthentication::getRegisterUrl($callbackUrl, $oauthCallbackUrl);

        return $this->redirectFactory->create()->setUrl($url);
    }
}
