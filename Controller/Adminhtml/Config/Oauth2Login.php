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
        private readonly PayplugAuthentication $payplugAuthentication,
        private readonly UrlInterface $urlBuilder,
        private readonly RequestInterface $request
    ) {
    }

    public function execute(): ResultInterface
    {
        $websiteId = $this->request->getParam('website');
        $callbackUrl = $this->urlBuilder->getUrl(
            'payplug_payments_admin/config/oauth2FetchAuthCode',
            ['website' => $websiteId]
        );
        $oauthCallbackUrl = $this->urlBuilder->getUrl(
            'payplug_payments_admin/config/oauth2FetchCredentials',
            ['website' => $websiteId]
        );

        $url = $this->payplugAuthentication::getRegisterUrl($callbackUrl, $oauthCallbackUrl);
        // TODO use lib php instead of this hack
        $url = str_replace('retail.service.payplug.com', 'retail.service-qa.payplug.com', $url);

        return $this->redirectFactory->create()->setUrl($url);
    }
}
