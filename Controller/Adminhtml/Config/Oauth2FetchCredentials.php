<?php
declare(strict_types=1);

namespace Payplug\Payments\Controller\Adminhtml\Config;

use Exception;
use Magento\Backend\Model\Auth\Session as AdminAuthSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Cache\Type\Config;
use Magento\Framework\App\Cache\TypeListInterface as CacheTypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface as ConfigWriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Payplug\Authentication as PayplugAuthentication;
use Payplug\Core\HttpClient;
use Payplug\Exception\ConfigurationException;
use Payplug\Exception\ConfigurationNotSetException;
use Payplug\Exception\ConnectionException;
use Payplug\Exception\HttpException;
use Payplug\Exception\UnexpectedAPIResponseException;
use Payplug\Payments\Logger\Logger;
use Payplug\Payplug;

class Oauth2FetchCredentials implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly MessageManagerInterface $messageManager,
        private readonly RedirectFactory $redirectFactory,
        private readonly Logger $logger,
        private readonly AdminAuthSession $adminAuthSession,
        private readonly ConfigWriterInterface $configWriter,
        private readonly SerializerInterface $serializer,
        private readonly CacheTypeListInterface $cacheTypeList
    ) {
    }

    public function execute(): Redirect
    {
        $code = $this->request->getParam('code');
        $oauth2Params = $this->adminAuthSession->getData('payplug_oauth2_params');

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

            //            print_r($payloadDecode['sid']);
            //            die();

            /**
             * Fetching Client Secret, needed for creating JWT
             */
            //            Payplug::init([
            //                'secretKey' => $jwtOneShopResponse['httpResponse']['access_token']
            //            ]);

            //            $payplug = new Payplug($jwtOneShopResponse['httpResponse']['access_token']);
            //
            //            $result = PayplugAuthentication::createClientIdAndSecret(
            //                $oauth2Params['company_id'],
            //                'client_name',
            //                'live',
            //                $jwtOneShopResponse['httpResponse']['access_token'],
            //                $payplug
            //            );
            //
            //            Payplug::init([
            //                'secretKey' => $jwtOneShopResponse['httpResponse']['access_token']
            //            ]);
            //            $result = $this->getClientData($payloadDecode['sid']);
            //
            //            print_r($result);
            //            die();

            /**
             * Create first JWT, and store into database
             * Store $payloadDecode['email'] into database for admin information only
             */
            $clientId = 'test2';
            $clientSecret = 'test2';
            //            $result = PayplugAuthentication::generateJWT($clientId, $clientSecret);
            //            print_r($result);
            //            die();



            $this->saveConfig('payplug_payments/oauth2/email', $payloadDecode['email']);
            $this->saveConfig('payplug_payments/oauth2/auth_data', $this->serializer->serialize([
                'test' => [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ],
                'live' => [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]
            ]));

            $this->saveConfig('payplug_payments/oauth2/token_data', $this->serializer->serialize([
                'test' => [],
                'live' => []
            ]));

            $this->cacheTypeList->cleanType(Config::TYPE_IDENTIFIER);

            /**
             * Unset payplug oauth onshot params for initiate oauth only
             */
            $this->adminAuthSession->setData('payplug_oauth2_params', null);

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
     * @throws ConnectionException
     * @throws ConfigurationNotSetException
     * @throws UnexpectedAPIResponseException
     * @throws HttpException
     * @throws ConfigurationException
     */
    private function getClientData(string $session): array
    {
        $payplug = Payplug::getDefaultConfiguration();
        $kratosSession = $this->setKratosSession($session);

        $httpClient = new HttpClient($payplug);
        //        $response = $httpClient->get(APIRoutes::$USER_MANAGER_RESOURCE, null, $kratosSession);
        $response = $httpClient->get('https://api-qa.payplug.com/user_manager', null, $kratosSession);
        $result = [];
        foreach ($response['httpResponse'] as $client) {
            $result[] = [
                'client_id' => $client['client_id'],
                'client_secret_mask' => $client['client_secret_mask'],
                'client_name' => $client['client_name'],
                'client_type' => $client['client_type'],
                'mode' => $client['mode'],

            ];
        }

        return $result;
    }

    /**
     * @throws ConfigurationException
     */
    private function setKratosSession($session): string
    {
        if (empty($session)) {
            throw new ConfigurationException('The session value must be set.');
        }

        return 'ory_kratos_session=' . $session;
    }
}
