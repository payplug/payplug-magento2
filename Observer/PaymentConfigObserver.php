<?php

namespace Payplug\Payments\Observer;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\ScopeInterface;
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
    public function __construct(
        Http $request,
        Login $login,
        Config $helper,
        ManagerInterface $messageManager
    ) {
        $this->request = $request;
        $this->login = $login;
        $this->helper = $helper;
        $this->messageManager = $messageManager;
    }

    /**
     * @param EventObserver $observer
     */
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
        if ($this->canProcessSection($postParams, 'payplug_payments_standard')) {
            $this->processStandardConfig($postParams['groups']);
        }
        if ($this->canProcessSection($postParams, 'payplug_payments_installment_plan')) {
            $this->processInstallmentPlanConfig($postParams['groups']);
        }
        if ($this->canProcessSection($postParams, 'payplug_payments_ondemand')) {
            $this->processOndemandConfig($postParams['groups']);
        }
        if ($this->canProcessSection($postParams, 'payplug_payments_oney') ||
            $this->canProcessSection($postParams, 'payplug_payments_oney_without_fees')
        ) {
            $this->processOneyConfig($postParams['groups']);
        }
    }

    /**
     * @param array  $postParams
     * @param string $sectionCode
     *
     * @return bool
     */
    private function canProcessSection($postParams, $sectionCode)
    {
        $sections = $postParams['config_state'];
        foreach ($sections as $sectionKey => $value) {
            if (strpos($sectionKey, $sectionCode) !== false) {
                if (isset($postParams['groups'][$sectionCode]['fields'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array $groups
     */
    private function processGeneralConfig($groups)
    {
        $fields = $groups['general']['fields'];

        $this->helper->initScopeData();

        $this->payplugConfigConnected = $this->helper->isConnected();
        $this->payplugConfigVerified = (bool) $this->getConfig('verified');

        $this->checkWebsiteScopeData($groups, $fields);

        //determine which kind of config is this call
        $isInit = false;
        $isLive = false;
        if (isset($fields['email']['value'])) {
            $isInit = true;
        }
        if (isset($fields['environmentmode']['value'])
            && $fields['environmentmode']['value'] == Config::ENVIRONMENT_LIVE
        ) {
            $isLive = true;
        }

        $pwd = null;
        if (isset($fields['pwd']['value'])) {
            $pwd = $fields['pwd']['value'];
        }

        if ($isInit) {
            $this->processInit($pwd, $fields);
        }
        if ($isLive && !$isInit) {
            $this->processLive($pwd);
        }

        if (!$this->payplugConfigConnected) {
            unset($groups['general']['fields']['pwd']);
            unset($groups['general']['fields']['email']);
            $this->saveConfig('connected', 0);
        }
        if (!$this->payplugConfigVerified && $isLive) {
            $groups['general']['fields']['environmentmode']['value']
                = Config::ENVIRONMENT_TEST;
            $this->saveConfig('verified', 0);
            $this->messageManager->addErrorMessage(__('You are able to perform only TEST transactions.'));
        }

        if ($this->payplugConfigConnected) {
            $apiKey = $this->testApiKey ?? $this->getConfig('test_api_key');
        }
        // Get live permissions only if account is verified and environment is switched to live
        if ($this->payplugConfigVerified && $isLive) {
            $apiKey = $this->liveApiKey ?? $this->getConfig('live_api_key');
        }
        if (!empty($apiKey)) {
            $this->getAccountPermissions($apiKey);
        }

        $this->request->setPostValue('groups', $groups);
    }

    /**
     * @param array $groups
     */
    private function processStandardConfig($groups)
    {
        $fields = $groups['payplug_payments_standard']['fields'];

        $this->helper->initScopeData();
        $groups = $this->validatePayplugConnection($fields, $groups, 'payplug_payments_standard');

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

    /**
     * @param array $groups
     */
    private function processInstallmentPlanConfig($groups)
    {
        $fields = $groups['payplug_payments_installment_plan']['fields'];

        $this->helper->initScopeData();
        $groups = $this->validatePayplugConnection($fields, $groups, 'payplug_payments_installment_plan');

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
     * @param array $groups
     */
    private function processOndemandConfig($groups)
    {
        $fields = $groups['payplug_payments_ondemand']['fields'];

        $this->helper->initScopeData();
        $groups = $this->validatePayplugConnection($fields, $groups, 'payplug_payments_ondemand');

        $this->request->setPostValue('groups', $groups);
    }

    /**
     * @param array $groups
     */
    private function processOneyConfig($groups)
    {
        $oneyGroups = [
            'payplug_payments_oney' => [],
            'payplug_payments_oney_without_fees' => [],
        ];

        $bothActive = true;
        foreach (array_keys($oneyGroups) as $oney) {
            $fields = $groups[$oney]['fields'];

            $this->helper->initScopeData();
            $groups = $this->validatePayplugConnection($fields, $groups, $oney);

            $oneyGroups[$oney]['current'] = $this->getConfig('active', sprintf('payment/%s/', $oney));

            $isActive = false;
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

                    if (empty($permissions['can_use_oney'])) {
                        $groups[$oney]['fields']['active']['value'] = 0;
                        $this->messageManager->addErrorMessage(
                            __('You don\'t have access to this feature yet. To activate the Oney guaranteed split payment, go to your PayPlug portal: %1.', 'https://portal-qa.payplug.com/#/configuration/oney')
                        );
                    } else {
                        $isActive = true;
                    }
                }
            }
            $bothActive = $bothActive && $isActive;
        }
        if ($bothActive) {
            $this->messageManager->addErrorMessage(
                __('Please note: it is impossible to offer Oney and Oney with no fees simultaneously in your store. You can only activate one of the two.')
            );
            foreach ($oneyGroups as $oney => $data) {
                $groups[$oney]['fields']['active']['value'] = $data['current'];
            }
        }

        $this->request->setPostValue('groups', $groups);
    }

    /**
     * @param array  $fields
     * @param array  $groups
     * @param string $fieldGroup
     *
     * @return array
     */
    private function validatePayplugConnection($fields, $groups, $fieldGroup)
    {
        if (!empty($fields['active']['value']) && !$this->helper->isConnected()) {
            $this->messageManager->addErrorMessage(
                __('You are not connected to a payplug account. ' .
                    'Please go to section Sales > Payplug Payments to log in.')
            );

            $groups[$fieldGroup]['fields']['active']['value'] = 0;
        }

        return $groups;
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
     * @param string|null $pwd
     * @param array       $fields
     */
    private function processInit($pwd, $fields)
    {
        $email = $fields['email']['value'];
        if (!$this->payplugLogin($email, $pwd, true)) {
            $this->payplugConfigConnected = false;
            $this->payplugConfigVerified = false;
        }
    }

    /**
     * @param string|null $pwd
     */
    private function processLive($pwd)
    {
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

    /**
     * @param string $field
     * @param string $path
     *
     * @return mixed
     */
    private function getConfig($field, $path = Config::CONFIG_PATH)
    {
        return $this->helper->getConfigValue($field, ScopeInterface::SCOPE_STORE, null, $path);
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
            'oney_countries' => $this->getConfig('oney_countries'),
            'oney_min_amounts' => $this->getConfig('oney_min_amounts'),
            'oney_max_amounts' => $this->getConfig('oney_max_amounts'),
        ];
        if (isset($jsonAnswer['configuration'])) {
            if (!empty($jsonAnswer['configuration']['currencies'])) {
                $configuration['currencies'] = [];
                foreach ($jsonAnswer['configuration']['currencies'] as $value) {
                    $configuration['currencies'][] = $value;
                }
            }
            if (!empty($jsonAnswer['configuration']['min_amounts'])) {
                $configuration['min_amounts'] = $this->processAmounts($jsonAnswer['configuration']['min_amounts']);
            }
            if (!empty($jsonAnswer['configuration']['max_amounts'])) {
                $configuration['max_amounts'] = $this->processAmounts($jsonAnswer['configuration']['max_amounts']);
            }
            if (!empty($jsonAnswer['configuration']['oney'])) {
                if (isset($jsonAnswer['configuration']['oney']['allowed_countries']) &&
                    is_array($jsonAnswer['configuration']['oney']['allowed_countries'])
                ) {
                    $oneyCountries = $jsonAnswer['configuration']['oney']['allowed_countries'];
                    $configuration['oney_countries'] = json_encode($oneyCountries);
                }
                if (!empty($jsonAnswer['configuration']['oney']['min_amounts'])) {
                    $configuration['oney_min_amounts'] = $this->processAmounts($jsonAnswer['configuration']['oney']['min_amounts']);
                }
                if (!empty($jsonAnswer['configuration']['oney']['max_amounts'])) {
                    $configuration['oney_max_amounts'] = $this->processAmounts($jsonAnswer['configuration']['oney']['max_amounts']);
                }
            }
        }

        $currencies = implode(';', $configuration['currencies']);
        $this->saveConfig('currencies', $currencies);
        $this->saveConfig('min_amounts', $configuration['min_amounts']);
        $this->saveConfig('max_amounts', $configuration['max_amounts']);
        $this->saveConfig('oney_countries', $configuration['oney_countries']);
        $this->saveConfig('oney_min_amounts', $configuration['oney_min_amounts']);
        $this->saveConfig('oney_max_amounts', $configuration['oney_max_amounts']);
        $this->saveConfig('company_id', $id);

        $permissions = [
            'use_live_mode',
            'can_save_cards',
            'can_create_installment_plan',
            'can_create_deferred_payment',
            'can_use_oney',
        ];
        foreach ($permissions as $permission) {
            $this->saveConfig($permission, (int)$jsonAnswer['permissions'][$permission] ?? 0);
        }

        return $jsonAnswer['permissions'];
    }

    /**
     * @param array $amounts
     *
     * @return string
     */
    private function processAmounts($amounts)
    {
        $configuration = '';
        foreach ($amounts as $key => $value) {
            if ($configuration !== '') {
                $configuration .= ';';
            }
            $configuration .= $key.':'.$value;
        }

        return $configuration;
    }
}
