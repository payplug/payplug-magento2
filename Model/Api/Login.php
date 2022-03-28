<?php

namespace Payplug\Payments\Model\Api;

use Payplug\Authentication;
use Payplug\Exception\BadRequestException;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Logger\Logger;
use Payplug\Payplug;

class Login
{
    /**
     * @var Authentication
     */
    private $authentication;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param Authentication $authentication
     * @param Logger         $logger
     */
    public function __construct(Authentication $authentication, Logger $logger)
    {
        $this->authentication = $authentication;
        $this->logger = $logger;
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
        } catch (BadRequestException $e) {
            // A BadRequestException is thrown after receiving a 400 from the API for bad credentials
            $this->logger->error($e->__toString());
            $result['message'] = __('The email and/or password was not correct.');
        } catch (PayplugException $e) {
            $this->logger->error($e->__toString());
            $result['message'] = __('Error while executing cURL request. Please check payplug logs.');
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $result['message'] = __('Error while executing cURL request. Please check payplug logs.');
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
        } catch (PayplugException $e) {
            $this->logger->error($e->__toString());
            $result['message'] = __('Error while executing cURL request. Please check payplug logs.');
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $result['message'] = $e->getMessage();
        }

        return $result;
    }
}
