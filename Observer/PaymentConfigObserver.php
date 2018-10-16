<?php

namespace Payplug\Payments\Observer;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Message\ManagerInterface;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Model\Api\Login;
use Payplug\Payments\Model\PaymentMethod;

class PaymentConfigObserver implements ObserverInterface
{
    /**
     * @var Http
     */
    private $request;

    /**
     * @var Login
     */
    private $login;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var string
     */
    private $scope;

    /**
     * @var bool
     */
    private $payplugConfigConnected;

    /**
     * @var bool
     */
    private $payplugConfigVerified;

    /**
     * @var bool
     */
    private $payplugConfigPremium;

    /**
     * @param Http             $request
     * @param Login            $login
     * @param Data             $helper
     * @param ManagerInterface $messageManager
     */
    public function __construct(Http $request, Login $login, Data $helper, ManagerInterface $messageManager)
    {
        $this->request = $request;
        $this->login = $login;
        $this->helper = $helper;
        $this->messageManager = $messageManager;
    }

    public function execute(EventObserver $observer)
    {
        $postParams = $this->request->getPost();

        if (!isset($postParams['groups']['payplug_payments']['fields'])) {
            return;
        }

        $groups = $postParams['groups'];
        $fields = $groups['payplug_payments']['fields'];

        $this->initScopeData();

        $this->payplugConfigConnected = $this->helper->isConnected();
        $this->payplugConfigVerified = (bool) $this->getConfig('verified');
        $this->payplugConfigPremium = (bool) $this->getConfig('premium');

        if ($this->scope == 'websites') {
            $fieldsRequiredForInit = [
                'email',
                'pwd',
                'environmentmode',
                'payment_page',
                'one_click',
            ];
            if (!$this->payplugConfigConnected) {
                // To connect on website level, all fields must be provided
                $allDefined = true;
                foreach ($fieldsRequiredForInit as $field) {
                    if (isset($fields[$field]['value'])) {
                        foreach ($fieldsRequiredForInit as $fieldCheck) {
                            if (!isset($fields[$fieldCheck]['value'])) {
                                $allDefined = false;
                            }
                        }
                    }
                }

                if (!$allDefined) {
                    foreach ($fieldsRequiredForInit as $field) {
                        if (isset($fields[$field]['value'])) {
                            unset($fields[$field]['value']);
                        }
                        if (isset($groups['payplug_payments']['fields'][$field])) {
                            unset($groups['payplug_payments']['fields'][$field]);
                        }
                    }
                    $this->messageManager->addErrorMessage(__('All fields must be defined when trying to connect at website level.'));
                }
            } else {
                // Once connected on website level, the only way to use global configuration is to disconnect
                foreach ($fieldsRequiredForInit as $field) {
                    if (isset($fields[$field]['inherit'])) {
                        unset($groups['payplug_payments']['fields'][$field]);
                    }
                }
            }
        }

        //determine which kind of config is this call
        $config = [
            'init' => false,
            'live' => false,
            'one_click' => false,
        ];
        if (isset($fields['email']['value'])) {
            $config['init'] = true;
        }
        if (isset($fields['environmentmode']['value'])
            && $fields['environmentmode']['value'] == PaymentMethod::ENVIRONMENT_LIVE
        ) {
            $config['live'] = true;
        }
        if (isset($fields['one_click']['value'])
            && $fields['one_click']['value'] == 1
        ) {
            $config['one_click'] = true;
        }

        $pwd = null;
        if (isset($fields['pwd']['value'])) {
            $pwd = $fields['pwd']['value'];
        }

        if ($config['init']) {
            $email = $fields['email']['value'];
            if (!$this->payplugLogin($email, $pwd, true)) {
                $this->payplugConfigConnected = false;
                $this->payplugConfigVerified = false;
                $this->payplugConfigPremium = false;
            }
        }

        if ($config['live']) {
            $error = false;
            if (!$this->payplugConfigConnected) {
                $error = true;
            } elseif (!$this->payplugConfigVerified) {
                if ($pwd != null) {
                    $email = $this->getConfig('email');
                    if (!$this->payplugLogin($email, $pwd)) {
                        $error = true;
                    }
                } else {
                    $error = true;
                }
            }
            if ($error) {
                $this->payplugConfigVerified = false;
                $this->payplugConfigPremium = false;
            }
        }

        if ($config['one_click']) {
            $error = false;
            if (!$this->payplugConfigConnected) {
                $error = true;
            } elseif ((!$this->payplugConfigVerified || !$this->payplugConfigPremium)
                && $fields['environmentmode']['value']
                == PaymentMethod::ENVIRONMENT_LIVE
            ) {
                if ($pwd != null) {
                    $email = $this->getConfig('email');
                    if (!$this->payplugLogin($email, $pwd)) {
                        $error = true;
                    }
                } else {
                    $error = true;
                }
            }
            if ($error) {
                $this->payplugConfigPremium = false;
            }
        }

        if (!$this->payplugConfigConnected) {
            unset($groups['payplug_payments']['fields']['pwd']);
            unset($groups['payplug_payments']['fields']['email']);
            $this->saveConfig('connected', 0);
        } elseif (!$this->payplugConfigVerified) {
            $groups['payplug_payments']['fields']['environmentmode']['value']
                = PaymentMethod::ENVIRONMENT_TEST;
            $this->saveConfig('verified', 0);
            $this->messageManager->addErrorMessage(__('You are able to perform only TEST transactions.'));
        } elseif (!$this->payplugConfigPremium
            && $config['one_click']
            && $fields['environmentmode']['value'] == PaymentMethod::ENVIRONMENT_LIVE
        ) {
            $groups['payplug_payments']['fields']['one_click']['value'] = 0;
            $this->saveConfig('premium', 0);
            $this->messageManager->addErrorMessage(__('Only Premium accounts can use one click in LIVE mode.'));
        }

        $this->request->setPostValue('groups', $groups);
    }

    private function initScopeData()
    {
        $this->helper->initScopeData();
        $this->scope = $this->helper->getConfigScope();
    }

    /**
     * @param string $field
     *
     * @return mixed
     */
    private function getConfig($field)
    {
        return $this->helper->getAdminConfigValue($field);
    }

    /**
     * @param string $field
     * @param string $value
     */
    private function saveConfig($field, $value)
    {
        $this->helper->setAdminConfigValue($field, $value);
    }

    /**
     * Connect to payplug account
     * Handle flags for account connection, verification and premium
     *
     * @param string $email
     * @param string $pwd
     * @param bool   $canChangeConfigConnected
     *
     * @return bool
     */
    private function payplugLogin($email, $pwd, $canChangeConfigConnected = false)
    {
        $error = false;
        if (!\Zend_Validate::is($pwd, 'NotEmpty')) {
            $error = true;
            $this->messageManager->addErrorMessage(__('Password field was empty.'));
        }

        if (!\Zend_Validate::is($email, 'EmailAddress')) {
            $error = true;
            $this->messageManager->addErrorMessage(__('The email address is incorrect.'));
        }

        if ($error) {
            return false;
        }

        $login = $this->login->login($email, $pwd);

        if (!$login['status']) {
            $this->messageManager->addErrorMessage(__($login['message']));
            return false;
        }

        $testApiKey = $login['api_keys']['test_key'];
        $liveApiKey = $login['api_keys']['live_key'];

        $this->saveConfig('test_api_key', $testApiKey);
        $this->saveConfig('live_api_key', $liveApiKey);

        $apiKeyToUse = null;

        if ($canChangeConfigConnected) {
            $this->payplugConfigConnected = true;
            $this->saveConfig('connected', 1);
        }

        if (!empty($liveApiKey)) {
            $this->payplugConfigVerified = true;
            $apiKeyToUse = $liveApiKey;
            $this->saveConfig('verified', 1);
        } elseif (!empty($testApiKey)) {
            $apiKeyToUse = $testApiKey;
        }

        if (!empty($apiKeyToUse)) {
            $result = $this->login->getAccount($apiKeyToUse);
            if (!$result['status']) {
                $this->messageManager->addErrorMessage(__($result['message']));
                return false;
            }
            $permissions = $this->treatAccountResponse($result['answer']);
            $this->payplugConfigPremium = $permissions['can_save_cards'];
            $this->saveConfig('premium', (int) $this->payplugConfigPremium);
        }

        return true;
    }

    /**
     * Parse JSON Answer from PayPlug to save configurations and return permissions
     *
     * @param mixed $jsonAnswer
     *
     * @return array
     */
    public function treatAccountResponse($jsonAnswer)
    {
        $id = $jsonAnswer['id'];

        $configuration = [
            'currencies' => $this->getConfig('currencies'),
            'min_amounts' => $this->getConfig('min_amounts'),
            'max_amounts' => $this->getConfig('max_amounts'),
        ];
        if (isset($jsonAnswer['configuration'])) {
            if (
                isset($jsonAnswer['configuration']['currencies'])
                && !empty($jsonAnswer['configuration']['currencies'])
                && sizeof($jsonAnswer['configuration']['currencies'])
            ) {
                $configuration['currencies'] = [];
                foreach ($jsonAnswer['configuration']['currencies'] as $value) {
                    $configuration['currencies'][] = $value;
                }
            }
            if (
                isset($jsonAnswer['configuration']['min_amounts'])
                && !empty($jsonAnswer['configuration']['min_amounts'])
                && sizeof($jsonAnswer['configuration']['min_amounts'])
            ) {
                $configuration['min_amounts'] = '';
                foreach ($jsonAnswer['configuration']['min_amounts'] as $key => $value) {
                    $configuration['min_amounts'] .= $key.':'.$value.';';
                }
                $configuration['min_amounts'] = substr($configuration['min_amounts'], 0, -1);
            }
            if (
                isset($jsonAnswer['configuration']['max_amounts'])
                && !empty($jsonAnswer['configuration']['max_amounts'])
                && sizeof($jsonAnswer['configuration']['max_amounts'])
            ) {
                $configuration['max_amounts'] = '';
                foreach ($jsonAnswer['configuration']['max_amounts'] as $key => $value) {
                    $configuration['max_amounts'] .= $key.':'.$value.';';
                }
                $configuration['max_amounts'] = substr($configuration['max_amounts'], 0, -1);
            }
        }

        $permissions = [
            'use_live_mode' => $jsonAnswer['permissions']['use_live_mode'],
            'can_save_cards' => $jsonAnswer['permissions']['can_save_cards'],
        ];

        $currencies = implode(';', $configuration['currencies']);
        $this->saveConfig('currencies', $currencies);
        $this->saveConfig('min_amounts', $configuration['min_amounts']);
        $this->saveConfig('max_amounts', $configuration['max_amounts']);
        $this->saveConfig('company_id', $id);

        return $permissions;
    }
}
