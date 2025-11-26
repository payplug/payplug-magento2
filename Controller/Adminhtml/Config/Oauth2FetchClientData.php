<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\Adminhtml\Config;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AdminAuthSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface as ConfigWriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Payplug\Authentication as PayplugAuthentication;
use Payplug\Payments\Helper\Config as ConfigHelper;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Service\GetOauth2AccessTokenData;
use Payplug\Payplug;

class Oauth2FetchClientData extends Action implements HttpGetActionInterface
{
    public const PAYPLUG_OAUTH2_AUTHENTICATION_CONTEXT_DATA = 'payplug_oauth2_params';
    public const PAYPLUG_OAUTH2_BASE_ENVIRONMENT_MODE = 'test';
    public const PAYPLUG_OAUTH2_BASE_PAYMENT_PAGE_MODE = 'integrated';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly Logger $logger,
        private readonly AdminAuthSession $adminAuthSession,
        private readonly ConfigWriterInterface $configWriter,
        private readonly SerializerInterface $serializer,
        private readonly ReinitableConfigInterface $scopeConfig,
        private readonly GetOauth2AccessTokenData $getOauth2AccessTokenData,
        private readonly ConfigHelper $configHelper,
        private readonly EventManager $eventManager,
        private readonly TypeListInterface $typeList,
        private readonly EncryptorInterface $encryptor,
        Context $context
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $code = $this->request->getParam('code');
        $oauth2Params = $this->adminAuthSession->getData(self::PAYPLUG_OAUTH2_AUTHENTICATION_CONTEXT_DATA);
        $this->adminAuthSession->setData(self::PAYPLUG_OAUTH2_AUTHENTICATION_CONTEXT_DATA, null);

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

            if (empty($testClientDataResult['httpResponse'])) {
                throw new LocalizedException(__('Could not retrieve TEST credentials from Payplug Portal'));
            }

            /**
             * Store client data and merchant email into config
             */
            $clientData = [
                ConfigHelper::ENVIRONMENT_TEST => [
                    'client_id' => $testClientDataResult['httpResponse']['client_id'],
                    'client_secret' => $testClientDataResult['httpResponse']['client_secret']
                ]
            ];

            if (empty($liveClientDataResult['httpResponse'])) {
                $this->saveConfig(ConfigHelper::CONFIG_PATH . 'environmentmode', ConfigHelper::ENVIRONMENT_TEST);
                $this->messageManager->addWarningMessage(
                    __('You are able to perform only TEST transactions.')
                );
            } else {
                $clientData[ConfigHelper::ENVIRONMENT_LIVE] = [
                    'client_id' => $liveClientDataResult['httpResponse']['client_id'],
                    'client_secret' => $liveClientDataResult['httpResponse']['client_secret']
                ];
            }

            $clientDataValue = $this->serializer->serialize($clientData);

            $this->saveConfig(
                ConfigHelper::OAUTH_CONFIG_PATH . ConfigHelper::OAUTH_CLIENT_DATA,
                $this->encryptor->encrypt($clientDataValue)
            );

            $this->scopeConfig->reinit();

            /**
             * Create first JWT for selected env mode
             */
            $websiteId = $this->getWebsiteId();
            $this->getOauth2AccessTokenData->execute($this->getWebsiteId(), true);

            /**
             * Store merchant email into config
             */
            $this->saveConfig(ConfigHelper::OAUTH_CONFIG_PATH . ConfigHelper::OAUTH_EMAIL, $payloadDecode['email']);

            /**
             * Cleanup legacy auth config
             */
            $this->configHelper->initScopeData();
            $this->configHelper->clearLegacyAuthConfig();

            /**
             * Simulate and dispatch the after save to create the default payment config
             */
            $this->request->setPostValue($this->getBasePostParams($websiteId ?: 0));
            $this->eventManager->dispatch(
                'controller_action_predispatch_adminhtml_system_config_save',
                ['request' => $this->request]
            );

            $this->typeList->cleanType('config');

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

    /**
     * We need to simulate an admin save after the Oauth2 first connexion
     */
    private function getBasePostParams(int $websiteId): array
    {
        $post = [
            'form_key' => $this->request->getParam('form_key'),
            'config_state' => [
                'payplug_payments_general' => 1,
                'payplug_payments_oauth2' => 1,
            ],
            'groups' => [
                'general' => [
                    'fields' => [
                        'environmentmode' => [
                            'value' => Oauth2FetchClientData::PAYPLUG_OAUTH2_BASE_ENVIRONMENT_MODE
                        ],
                        'payment_page' => [
                            'value' => Oauth2FetchClientData::PAYPLUG_OAUTH2_BASE_PAYMENT_PAGE_MODE
                        ],
                    ],
                ],
            ],
            'payplug_payments_is_connected' => 0,
            'payplug_payments_is_verified' => 0,
            'payplug_payments_can_override_default' => 1,
            'website' => $websiteId
        ];

        return $post;
    }
}
