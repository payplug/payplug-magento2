<?php
declare(strict_types=1);

namespace Payplug\Payments\Controller\Adminhtml\Config;

use Exception;
use Magento\Backend\Model\Auth\Session as AdminAuthSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface as ConfigWriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Payplug\Authentication as PayplugAuthentication;
use Payplug\Payments\Helper\Config as ConfigHelper;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Service\RenewOauth2AccessToken;
use Payplug\Payplug;

class Oauth2FetchClientData implements HttpGetActionInterface
{
    public const PAYPLUG_OAUTH2_AUTHENTICATION_CONTEXT_DATA = 'payplug_oauth2_params';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly MessageManagerInterface $messageManager,
        private readonly RedirectFactory $redirectFactory,
        private readonly Logger $logger,
        private readonly AdminAuthSession $adminAuthSession,
        private readonly ConfigWriterInterface $configWriter,
        private readonly SerializerInterface $serializer,
        private readonly ReinitableConfigInterface $scopeConfig,
        private readonly RenewOauth2AccessToken $renewOauth2AccessToken
    ) {
    }

    public function execute(): Redirect
    {
        $code = $this->request->getParam('code');
        $oauth2Params = $this->adminAuthSession->getData(self::PAYPLUG_OAUTH2_AUTHENTICATION_CONTEXT_DATA);

        try {
            /**
             * Generate JWT OneShot, needed for fetching client secret
             */
            $jwtOneShopResponse = PayplugAuthentication::generateJWTOneShot(
                $code,
                $oauth2Params['callback_url'],
                $oauth2Params['client_id'],
                $oauth2Params['code_verifier'],
            );
            $idToken = $jwtOneShopResponse['httpResponse']['id_token'];
            $idTokenSplit = explode('.', $idToken);
            $payload = base64_decode($idTokenSplit[1]);
            $payloadDecode = json_decode($payload, true);

            /**
             * Fetching Client Secret, needed for creating JWT
             */
            Payplug::init([
                'secretKey' => $jwtOneShopResponse['httpResponse']['access_token']
            ]);

            $clientName = 'Magento';

            $testClientDataResult = PayplugAuthentication::createClientIdAndSecret(
                $oauth2Params['company_id'],
                $clientName,
                ConfigHelper::ENVIRONMENT_TEST
            );

            $liveClientDataResult = PayplugAuthentication::createClientIdAndSecret(
                $oauth2Params['company_id'],
                $clientName,
                ConfigHelper::ENVIRONMENT_LIVE
            );

            /**
             * Store data into config
             */
            $this->saveConfig('payplug_payments/oauth2/email', $payloadDecode['email']);
            $this->saveConfig('payplug_payments/oauth2/client_data', $this->serializer->serialize([
                ConfigHelper::ENVIRONMENT_TEST => [
                    'client_id' => $testClientDataResult['httpResponse']['client_id'],
                    'client_secret' => $testClientDataResult['httpResponse']['client_secret']
                ],
                ConfigHelper::ENVIRONMENT_LIVE => [
                    'client_id' => $liveClientDataResult['httpResponse']['client_id'],
                    'client_secret' => $liveClientDataResult['httpResponse']['client_secret']
                ]
            ]));

            $this->scopeConfig->reinit();

            /**
             * Create first JWT for selected env mode
             */
            $websiteId = $this->getWebsiteId();
            $currentMode = $this->scopeConfig->getValue(
                'payplug_payments/oauth2/mode',
                $websiteId ? StoreScopeInterface::SCOPE_WEBSITES : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                $websiteId ?: 0
            );

            $clientDataResult = ($currentMode === ConfigHelper::ENVIRONMENT_TEST) ? $testClientDataResult
                : $liveClientDataResult;
            $clientId = $clientDataResult['httpResponse']['client_id'];
            $clientSecret = $clientDataResult['httpResponse']['client_secret'];
            $this->renewOauth2AccessToken->execute($this->getWebsiteId(), true, $clientId, $clientSecret);

            /**
             * Unset payplug oauth onshot params for initiate oauth only
             */
            $this->adminAuthSession->setData(self::PAYPLUG_OAUTH2_AUTHENTICATION_CONTEXT_DATA, null);

            $this->messageManager->addSuccessMessage(__('Oauth2 authentication successful'));
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            $this->messageManager->addErrorMessage(__('Could not fetch credentials from Payplug Portal'));
        }

        return $this->redirectFactory->create()->setPath(
            'adminhtml/system_config/edit',
            ['section' => 'payplug_payments', 'website' => $this->getWebsiteId()]
        );
    }

    private function saveConfig(string $path, string $value): void
    {
        $websiteId = $this->getWebsiteId();

        $this->configWriter->save(
            $path,
            $value,
            $websiteId ? StoreScopeInterface::SCOPE_WEBSITES : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $websiteId ?: 0
        );
    }

    private function getWebsiteId(): int
    {
        return (int)$this->request->getParam('website');
    }
}
