<?php
declare(strict_types=1);

namespace Payplug\Payments\Controller\Adminhtml\Config;

use Exception;
use Magento\Backend\Model\Auth\Session as AdminAuthSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\UrlInterface;
use Payplug\Authentication as PayplugAuthentication;
use Payplug\Core\APIRoutes;
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
        private readonly PayplugAuthentication $payplugAuthentication,
        private readonly RequestInterface $request,
        private readonly MessageManagerInterface $messageManager,
        private readonly RedirectFactory $redirectFactory,
        private readonly Logger $logger,
        private readonly AdminAuthSession $adminAuthSession,
    ) {
    }

    public function execute(): Redirect
    {
        $code = $this->request->getParam('code');

        $clientId = $this->adminAuthSession->getData('client_id');
        $codeVerifier = $this->adminAuthSession->getData('code_verifier');
        $callbackUrl = $this->adminAuthSession->getData('callback_url');

        try {
            $jwtOneShopResponse = $this->payplugAuthentication::generateJWTOneShot(
                $code,
                $callbackUrl,
                $clientId,
                $codeVerifier
            );
            $idToken = $jwtOneShopResponse['httpResponse']['id_token'];
            $idTokenSplit = explode('.', $idToken);
            $payload = base64_decode($idTokenSplit[1]);
            $payloadDecode = json_decode($payload, true);

            /**
             * TODO Fetch Oauth2 credentials (client_id/client_secret)
             */
//            $result = $this->getClientData($payloadDecode['access_token']);

            /**
             * TODO Fetch First JWT for API calls, then store into config
             */

            /**
             * TODO store into database for UI use
             */
//            $email = $payloadDecode['email'];

            $this->adminAuthSession->setData('client_id', null);
            $this->adminAuthSession->setData('code_verifier', null);
            $this->adminAuthSession->setData('callback_url', null);

            $this->messageManager->addSuccessMessage(__('Oauth2 authentication successful'));
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            $this->messageManager->addErrorMessage(__('Could not fetch credentials from Payplug Portal'));
        }

        return $this->redirectFactory->create()->setPath(
            'adminhtml/system_config/edit',
            ['section' => 'payplug_payments']
        );
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
        $response = $httpClient->get(APIRoutes::$USER_MANAGER_RESOURCE, null, $kratosSession);
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
