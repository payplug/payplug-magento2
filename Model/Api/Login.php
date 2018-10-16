<?php

namespace Payplug\Payments\Model\Api;

use Payplug\Authentication;
use Payplug\Payplug;

class Login
{
    /**
     * @var Authentication
     */
    private $authentication;

    public function __construct(Authentication $authentication)
    {
        $this->authentication = $authentication;
    }

    /**
     * Send cURL request to PayPlug to check given credentials and get API Keys
     *
     * @param string $email
     * @param string $password
     *
     * @return array
     */
    public function login($email, $password)
    {
        $result = [
            'status' => false,
            'message' => null,
            'api_keys' => null,
        ];
        try {
            $answer = $this->authentication->getKeysByLogin($email, $password);

            $apiKeys = [];
            $apiKeys['test_key'] = '';
            $apiKeys['live_key'] = '';

            if (isset($answer['httpResponse']['secret_keys'])) {
                if (isset($answer['httpResponse']['secret_keys']['test'])) {
                    $apiKeys['test_key'] = $answer['httpResponse']['secret_keys']['test'];
                }
                if (isset($answer['httpResponse']['secret_keys']['live'])) {
                    $apiKeys['live_key'] = $answer['httpResponse']['secret_keys']['live'];
                }
            }

            $result['status'] = true;
            $result['api_keys'] = $apiKeys;
        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Send cURL request to PayPlug to get permissions
     *
     * @param string $apiKey
     *
     * @return array
     */
    public function getAccount($apiKey)
    {
        Payplug::setSecretKey($apiKey);
        $result = [
            'status' => false,
            'message' => null,
            'answer' => null,
        ];

        try {
            $answer = $this->authentication->getAccount();
            $result['status'] = true;
            $result['answer'] = $answer['httpResponse'];
        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }
}
