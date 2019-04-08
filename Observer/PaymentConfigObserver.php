<?php

namespace Payplug\Payments\Observer;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Message\ManagerInterface;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Model\Api\Login;

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
     * @var Config
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
     * @var string
     */
    private $testApiKey;

    /**
     * @var string
     */
    private $liveApiKey;

    /**
     * @param Http             $request
     * @param Login            $login
     * @param Config           $helper
     * @param ManagerInterface $messageManager
     */
    public function __construct(Http $request, Login $login, Config $helper, ManagerInterface $messageManager)
    {
        $this->request = $request;
        $this->login = $login;
        $this->helper = $helper;
        $this->messageManager = $messageManager;
    }

    public function execute(EventObserver $observer)
    {
        $postParams = $this->request->getPost();

        if (!isset($postParams['config_state'])) {
            return;
        }

        $sections = $postParams['config_state'];
        if (isset($sections['payplug_payments_general']) && isset($postParams['groups']['general']['fields'])) {
            $this->processGeneralConfig($postParams['groups']);
            return;
        }
        if (isset($sections['payment_us_payplug_payments_standard']) &&
            isset($postParams['groups']['payplug_payments_standard']['fields'])
        ) {
            $this->processStandardConfig($postParams['groups']);
        }
        if (isset($sections['payment_us_payplug_payments_installment_plan']) &&
            isset($postParams['groups']['payplug_payments_installment_plan']['fields'])
        ) {
            $this->processInstallmentPlanConfig($postParams['groups']);
        }
    }

    private function processGeneralConfig($groups)
    {
        $fields = $groups['general']['fields'];

        $this->helper->initScopeData();

        $this->payplugConfigConnected = $this->helper->isConnected();
        $this->payplugConfigVerified = (bool) $this->getConfig('verified');

        $this->checkWebsiteScopeData($groups, $fields);

        //determine which kind of config is this call
        $config = [
            'init' => false,
            'live' => false,
        ];
        if (isset($fields['email']['value'])) {
            $config['init'] = true;
        }
        if (isset($fields['environmentmode']['value'])
            && $fields['environmentmode']['value'] == Config::ENVIRONMENT_LIVE
        ) {
            $config['live'] = true;
        }

        $pwd = null;
        if (isset($fields['pwd']['value'])) {
            $pwd = $fields['pwd']['value'];
        }

        $this->processInit($config, $pwd, $fields);
        $this->processLive($config, $pwd);

        if (!$this->payplugConfigConnected) {
            unset($groups['general']['fields']['pwd']);
            unset($groups['general']['fields']['email']);
            $this->saveConfig('connected', 0);
        }
        if (!$this->payplugConfigVerified) {
            $groups['general']['fields']['environmentmode']['value']
                = Config::ENVIRONMENT_TEST;
            $this->saveConfig('verified', 0);
            $this->messageManager->addErrorMessage(__('You are able to perform only TEST transactions.'));
        }

        if ($this->payplugConfigConnected) {
            $apiKey = $this->testApiKey;
        }
        // Get live permissions only if account is verified and environment is switched to live
        if ($this->payplugConfigVerified && $config['live']) {
            $apiKey = $this->liveApiKey;
        }
        if (!empty($apiKey)) {
            $this->getAccountPermissions($apiKey);
        }

        $this->request->setPostValue('groups', $groups);
    }

    private function processStandardConfig($groups)
    {
        $fields = $groups['payplug_payments_standard']['fields'];

        $this->helper->initScopeData();

        if (!empty($fields['active']['value']) && !$this->helper->isConnected()) {
            $this->messageManager->addErrorMessage(
                __('You are not connected to a payplug account. ' .
                    'Please go to section Sales > Payplug Payments to log in.')
            );

            $groups['payplug_payments_standard']['fields']['active']['value'] = 0;
        }

        if (!empty($fields['one_click']['value'])) {
            $environmentMode = $this->getConfig('environmentmode');

            $apiKey = $this->getConfig('test_api_key');
            if ($environmentMode == Config::ENVIRONMENT_LIVE) {
                $apiKey = $this->getConfig('live_api_key');
            }

            if (empty($apiKey)) {
                $this->messageManager->addErrorMessage(
                    __('We are not able to retrieve your account information. ' .
                        'Please go to section Sales > Payplug Payments to log in again.')
                );
            } else {
                $permissions = $this->getAccountPermissions($apiKey);

                if (empty($permissions['can_save_cards'])) {
                    $groups['payplug_payments_standard']['fields']['one_click']['value'] = 0;
                    if ($environmentMode == Config::ENVIRONMENT_LIVE) {
                        $this->messageManager->addErrorMessage(
                            __('Only Premium accounts can use one click in LIVE mode.')
                        );
                    }
                }
            }
        }

        $this->request->setPostValue('groups', $groups);
    }

    private function processInstallmentPlanConfig($groups)
    {
        $fields = $groups['payplug_payments_installment_plan']['fields'];

        $this->helper->initScopeData();

        if (!empty($fields['active']['value']) && !$this->helper->isConnected()) {
            $this->messageManager->addErrorMessage(
                __('You are not connected to a payplug account. ' .
                    'Please go to section Sales > Payplug Payments to log in.')
            );

            $groups['payplug_payments_installment_plan']['fields']['active']['value'] = 0;
        }

        if (!empty($fields['active']['value'])) {
            $environmentMode = $this->getConfig('environmentmode');

            $apiKey = $this->getConfig('test_api_key');
            if ($environmentMode == Config::ENVIRONMENT_LIVE) {
                $apiKey = $this->getConfig('live_api_key');
            }

            if (empty($apiKey)) {
                $this->messageManager->addErrorMessage(
                    __('We are not able to retrieve your account information. ' .
                        'Please go to section Sales > Payplug Payments to log in again.')
                );
            } else {
                $permissions = $this->getAccountPermissions($apiKey);

                if (empty($permissions['can_create_installment_plan'])) {
                    $groups['payplug_payments_installment_plan']['fields']['active']['value'] = 0;
                    if ($environmentMode == Config::ENVIRONMENT_LIVE) {
                        $this->messageManager->addErrorMessage(
                            __('Only Premium accounts can use installment plan in LIVE mode.')
                        );
                    }
                }
            }
        }

        if (isset($fields['threshold']['value']) && $fields['threshold']['value'] < 4) {
            $this->messageManager->addErrorMessage(__('Amount must be greater than 4€.'));
            $groups['payplug_payments_installment_plan']['fields']['threshold']['value'] = 4;
        }

        $this->request->setPostValue('groups', $groups);
    }

    /**
     * @param array &$groups
     * @param array &$fields
     */
    private function checkWebsiteScopeData(&$groups, &$fields)
    {
        if ($this->helper->getConfigScope() != 'websites') {
            return;
        }
        $fieldsRequiredForInit = [
            'email',
            'pwd',
            'environmentmode',
            'payment_page',
        ];
        if (!$this->payplugConfigConnected) {
            // To connect on website level, all fields must be provided
            if (!$this->checkRequiredFields($fieldsRequiredForInit, $fields)) {
                return;
            }
            foreach ($fieldsRequiredForInit as $field) {
                if (isset($fields[$field]['value'])) {
                    unset($fields[$field]['value']);
                }
                if (isset($groups['general']['fields'][$field])) {
                    unset($groups['general']['fields'][$field]);
                }
            }
            $this->messageManager->addErrorMessage(
                __('All fields must be defined when trying to connect at website level.')
            );
            return;
        }
        // Once connected on website level, the only way to use global configuration is to disconnect
        foreach ($fieldsRequiredForInit as $field) {
            if (isset($fields[$field]['inherit'])) {
                unset($groups['general']['fields'][$field]);
            }
        }
    }

    /**
     * @param array $fieldsRequiredForInit
     * @param array $fields
     *
     * @return bool
     */
    private function checkRequiredFields($fieldsRequiredForInit, $fields)
    {
        foreach ($fieldsRequiredForInit as $field) {
            if (isset($fields[$field]['value'])) {
                foreach ($fieldsRequiredForInit as $fieldCheck) {
                    if (!isset($fields[$fieldCheck]['value'])) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param array       $config
     * @param string|null $pwd
     * @param array       $fields
     */
    private function processInit($config, $pwd, $fields)
    {
        if ($config['init']) {
            $email = $fields['email']['value'];
            if (!$this->payplugLogin($email, $pwd, true)) {
                $this->payplugConfigConnected = false;
                $this->payplugConfigVerified = false;
            }
        }
    }

    /**
     * @param array       $config
     * @param string|null $pwd
     */
    private function processLive($config, $pwd)
    {
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
            }
        }
    }

    /**
     * @param string $field
     *
     * @return mixed
     */
    private function getConfig($field)
    {
        return $this->helper->getConfigValue($field);
    }

    /**
     * @param string $field
     * @param string $value
     */
    private function saveConfig($field, $value)
    {
        $this->helper->setConfigValue($field, $value);
    }

    /**
     * Connect to payplug account
     * Handle flags for account connection, verification
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

        $this->testApiKey = $login['api_keys']['test_key'];
        $this->liveApiKey = $login['api_keys']['live_key'];

        $this->saveConfig('test_api_key', $this->testApiKey);
        $this->saveConfig('live_api_key', $this->liveApiKey);

        if ($canChangeConfigConnected) {
            $this->payplugConfigConnected = true;
            $this->saveConfig('connected', 1);
        }

        if (!empty($this->liveApiKey)) {
            $this->payplugConfigVerified = true;
            $this->saveConfig('verified', 1);
        }

        return true;
    }

    /**
     * @param string $apiKey
     *
     * @return array
     */
    private function getAccountPermissions($apiKey)
    {
        $result = $this->login->getAccount($apiKey);
        if (!$result['status']) {
            $this->messageManager->addErrorMessage(__($result['message']));

            return [];
        }

        return $this->treatAccountResponse($result['answer']);
    }

    /**
     * Parse JSON Answer from PayPlug to save configurations and return permissions
     *
     * @param mixed $jsonAnswer
     *
     * @return array
     */
    private function treatAccountResponse($jsonAnswer)
    {
        $id = $jsonAnswer['id'];

        $configuration = [
            'currencies' => $this->getConfig('currencies'),
            'min_amounts' => $this->getConfig('min_amounts'),
            'max_amounts' => $this->getConfig('max_amounts'),
        ];
        if (isset($jsonAnswer['configuration'])) {
            if (!empty($jsonAnswer['configuration']['currencies'])) {
                $configuration['currencies'] = [];
                foreach ($jsonAnswer['configuration']['currencies'] as $value) {
                    $configuration['currencies'][] = $value;
                }
            }
            if (!empty($jsonAnswer['configuration']['min_amounts'])) {
                $configuration['min_amounts'] = '';
                foreach ($jsonAnswer['configuration']['min_amounts'] as $key => $value) {
                    $configuration['min_amounts'] .= $key.':'.$value.';';
                }
                $configuration['min_amounts'] = substr($configuration['min_amounts'], 0, -1);
            }
            if (!empty($jsonAnswer['configuration']['max_amounts'])) {
                $configuration['max_amounts'] = '';
                foreach ($jsonAnswer['configuration']['max_amounts'] as $key => $value) {
                    $configuration['max_amounts'] .= $key.':'.$value.';';
                }
                $configuration['max_amounts'] = substr($configuration['max_amounts'], 0, -1);
            }
        }

        $currencies = implode(';', $configuration['currencies']);
        $this->saveConfig('currencies', $currencies);
        $this->saveConfig('min_amounts', $configuration['min_amounts']);
        $this->saveConfig('max_amounts', $configuration['max_amounts']);
        $this->saveConfig('company_id', $id);

        return $jsonAnswer['permissions'];
    }
}
