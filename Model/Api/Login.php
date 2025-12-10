<?php

namespace Payplug\Payments\Model\Api;

use Exception;
use Payplug\Authentication;
use Payplug\Exception\BadRequestException;
use Payplug\Exception\NotFoundException;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Logger\Logger;
use Payplug\Payplug;

class Login
{
    /**
     * @param Authentication $authentication
     * @param Logger $logger
     */
    public function __construct(
        private readonly Authentication $authentication,
        private readonly Logger $logger
    ) {
    }

    /**
     * Login to PayPlug API
     *
     * @param string $email
     * @param string $password
     * @return array
     */
    public function login(string $email, string $password): array
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
        } catch (BadRequestException|NotFoundException $e) {
            // A BadRequestException is thrown after receiving a 400 from the API for bad credentials
            $this->logger->error($e->__toString());
            $result['message'] = __('The email and/or password was not correct.');
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            $result['message'] = __('Error while executing cURL request. Please check payplug logs.');
        }

        return $result;
    }

    /**
     * Get account information
     *
     * @param string $apiKey
     * @return array
     */
    public function getAccount(string $apiKey): array
    {
        $result = [
            'status' => false,
            'message' => null,
            'answer' => null,
        ];

        try {
            Payplug::init(['secretKey' => $apiKey]);
            $answer = $this->authentication->getAccount();

            $result['status'] = true;
            $result['answer'] = $answer['httpResponse'];
        } catch (PayplugException $e) {
            $this->logger->error($e->__toString());
            $result['message'] = __('Error while executing cURL request. Please check payplug logs.');
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            $result['message'] = $e->getMessage();
        }

        return $result;
    }
}
