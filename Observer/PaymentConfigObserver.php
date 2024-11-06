<?php

namespace Payplug\Payments\Observer;

use Laminas\Validator\EmailAddress;
use Laminas\Validator\NotEmpty;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Phrase;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
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
     * @var StoreManager
     */
    private $storeManager;

    /**
     * @var string
     */
    private $liveApiKey;

    /**
     * @var array
     */
    private $permissions = [];

    /**
     * @param Http             $request
     * @param Login            $login
     * @param Config           $helper
     * @param ManagerInterface $messageManager
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Http $request,
        Login $login,
        Config $helper,
        ManagerInterface $messageManager,
        StoreManagerInterface $storeManager
    ) {
        $this->request = $request;
        $this->login = $login;
        $this->helper = $helper;
        $this->messageManager = $messageManager;
        $this->storeManager = $storeManager;
    }

    /**
     * Handle PayPlug configuration save
     *
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
        if ($this->canProcessSection($postParams, 'payplug_payments_bancontact')) {
            $this->processBancontactConfig($postParams['groups']);
        }
        if ($this->canProcessSection($postParams, 'payplug_payments_apple_pay')) {
            $this->processApplePayConfig($postParams['groups']);
        }
        if ($this->canProcessSection($postParams, 'payplug_payments_amex')) {
            $this->processAmexConfig($postParams['groups']);
        }
        if ($this->canProcessSection($postParams, 'payplug_payments_satispay')) {
            $this->processSatispayConfig($postParams['groups']);
        }
        if ($this->canProcessSection($postParams, 'payplug_payments_ideal')) {
            $this->processIdealConfig($postParams['groups']);
        }
        if ($this->canProcessSection($postParams, 'payplug_payments_mybank')) {
            $this->processMybankConfig($postParams['groups']);
        }
    }

    /**
     * Check if posted data contains section info
     *
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
     * Handle General configuration
     *
     * @param array $groups
     */
    private function processGeneralConfig($groups)
    {
        $fields = $groups['general']['fields'];

        $this->helper->initScopeData();

        $this->payplugConfigConnected = $this->helper->isConnected();
        $this->payplugConfigVerified = (bool) $this->getConfig('verified');

        $this->checkWebsiteScopeData($groups, $fields);

        // Determine which kind of config is this call
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
            $permissions = $this->getAccountPermissions($apiKey);


            $payment_value = null;
            if(isset($fields['payment_page']['value'])){
              $payment_value = $fields['payment_page']['value'];
            }

            if(isset($fields['payment_page']['inherit'])){
              $payment_value = $fields['payment_page']['inherit'];
            }

            if ( $payment_value !== null && $payment_value == Config::PAYMENT_PAGE_INTEGRATED) {
                if ( !isset($permissions['can_use_integrated_payments']) || !$permissions['can_use_integrated_payments']) {
                    $paymentPage = $this->getConfig('payment_page');
                    if (empty($paymentPage) || $paymentPage === Config::PAYMENT_PAGE_INTEGRATED) {
                        $paymentPage = Config::PAYMENT_PAGE_REDIRECT;
                    }
                    $groups['general']['fields']['payment_page']['value'] = $paymentPage;
                    $this->messageManager->addErrorMessage(__(
                        'You do not have access to this feature yet. ' .
                        'To activate it, please fill in the following form: ' .
                        'https://support.payplug.com/hc/en-gb/requests/new?ticket_form_id=8138934372636'
                    ));
                }
            }
        }

        $this->request->setPostValue('groups', $groups);
    }

    /**
     * Handle Standard configuration
     *
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
     * Handle InstallmentPlan configuration
     *
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

        if (isset($fields['threshold']['value'])) {
            $errorMessage = __('The amount must be between €4 and €20,000.');
            if ($fields['threshold']['value'] < 4) {
                $this->messageManager->addErrorMessage($errorMessage);
                $groups['payplug_payments_installment_plan']['fields']['threshold']['value'] = 4;
            }
            if ($fields['threshold']['value'] > 20000) {
                $this->messageManager->addErrorMessage($errorMessage);
                $groups['payplug_payments_installment_plan']['fields']['threshold']['value'] = 20000;
            }
        }

        $this->request->setPostValue('groups', $groups);
    }

    /**
     * Handle Ondemand configuration
     *
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
     * Handle Oney configuration
     *
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
                        $errorMessage = 'You don\'t have access to this feature yet. ' .
                            'To activate the Oney guaranteed split payment, go to your PayPlug portal: %1.';
                        $this->messageManager->addErrorMessage(
                            __($errorMessage, 'https://portal-qa.payplug.com/#/configuration/oney')
                        );
                    } else {
                        $isActive = true;
                    }
                }

                // If customer loggedin && have permissions
                if($isActive) {
                    $storeId = $this->storeManager->getStore()->getId();
                    $currency = $this->storeManager->getStore()->getCurrentCurrencyCode();

                    $minAmountsConfig = $this->helper->getConfigValue('oney_min_amounts', ScopeInterface::SCOPE_STORE, $storeId, Config::ONEY_CONFIG_PATH) ?
                        $this->helper->getConfigValue('oney_min_amounts', ScopeInterface::SCOPE_STORE, $storeId, Config::ONEY_CONFIG_PATH) :
                        $this->helper->getConfigValue('oney_min_amounts', ScopeInterface::SCOPE_STORE, $storeId, Config::CONFIG_PATH);

                    $maxAmountsConfig = $this->helper->getConfigValue('oney_max_amounts', ScopeInterface::SCOPE_STORE, $storeId, Config::ONEY_CONFIG_PATH) ?
                        $this->helper->getConfigValue('oney_max_amounts', ScopeInterface::SCOPE_STORE, $storeId, Config::ONEY_CONFIG_PATH) :
                        $this->helper->getConfigValue('oney_max_amounts', ScopeInterface::SCOPE_STORE, $storeId, Config::CONFIG_PATH);

                    $oney_default_thresholds = $this->helper->getAmountsByCurrency($currency, $storeId, Config::CONFIG_PATH, 'oney_');

                    if( !$this->validateThresholdValues($fields, $oney_default_thresholds) ){

                        $this->messageManager->addErrorMessage(
                            __('The value is not within the specified range.')
                        );
                    }

                    // Website scope value
                    if(isset($groups[$oney]['fields']['oney_min_threshold']['value'])){
                      $min = $groups[$oney]['fields']['oney_min_threshold']['value'];
                      $max = $groups[$oney]['fields']['oney_max_threshold']['value'];
                    }else{

                      // Inherit value
                      $min = $groups[$oney]['fields']['oney_min_threshold']['inherit'];
                      $max = $groups[$oney]['fields']['oney_max_threshold']['inherit'];
                    }


                    // Save thresholds on the same format as general/oney_max_amount
                    $this->saveOneyConfig('oney_min_amounts', preg_replace(
                            "/(?<=:).*$/i",
                            $min * 100,
                            $minAmountsConfig
                        )
                    );

                    $this->saveOneyConfig('oney_max_amounts', preg_replace(
                        "/(?<=:).*$/i",
                      $max * 100,
                        $maxAmountsConfig
                    ));
                }

            }
            $bothActive = $bothActive && $isActive;

        }

        if ($bothActive) {
            $this->messageManager->addErrorMessage(
                __('Please note: it is impossible to offer Oney and Oney with no fees simultaneously in your store. ' .
                    'You can only activate one of the two.')
            );
            foreach ($oneyGroups as $oney => $data) {
                $groups[$oney]['fields']['active']['value'] = $data['current'];
            }
        }

        $this->request->setPostValue('groups', $groups);
    }

    private function validateThresholdValues($fields, $oney_thresholds){

        if(isset($fields['oney_min_threshold']["value"])){
          $min_threshold = intval($fields['oney_min_threshold']["value"]);
          $max_threshold = intval($fields['oney_max_threshold']["value"]);
        }

        // Website scope has on inherit
        elseif(isset($fields['oney_min_threshold']["inherit"])){
          $min_threshold = intval($fields['oney_min_threshold']["inherit"]);
          $max_threshold = intval($fields['oney_max_threshold']["inherit"]);

        }else{
         return false;

        }

        if($oney_thresholds === false){
          return false;
        }

        if($min_threshold >= $max_threshold){
            return false;
        }

        if($min_threshold < ($oney_thresholds["min_amount"]/100)){
            return false;
        }

        if($max_threshold > ($oney_thresholds["max_amount"]/100)){
            return false;
        }

        return true;

    }

    /**
     * Handle Bancontact configuration
     *
     * @param array $groups
     */
    private function processBancontactConfig($groups)
    {
        $this->processLiveOnlyMethod(
            $groups,
            'bancontact',
            __(
                'You do not have access to this feature yet. ' .
                'To activate Bancontact, please fill in the following form: %1 (%2)',
                'https://support.payplug.com/hc/fr/requests/new?ticket_form_id=4583813991452',
                'support@payplug.com'
            ),
            __(
                'Bancontact is unavailable in TEST mode. ' .
                'Please switch your Payplug plugin to LIVE mode to activate it.'
            )
        );
    }

    /**
     * Handle Bancontact configuration
     *
     * @param array $groups
     */
    private function processApplePayConfig($groups)
    {
        $fields = $groups['payplug_payments_apple_pay']['fields'];

        $this->helper->initScopeData();
        $groups = $this->validatePayplugConnection($fields, $groups, 'payplug_payments_apple_pay');

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
                if (empty($permissions['can_use_apple_pay'])) {
                    $groups['payplug_payments_apple_pay']['fields']['active']['value'] = 0;
                    $message = 'You don\'t have access to this feature yet. ' .
                        'To activate Apple Pay, please contact %1 and activate the LIVE mode.';
                    if ($environmentMode == Config::ENVIRONMENT_LIVE) {
                        $message = 'You don\'t have access to this feature yet. ' .
                            'To activate Apple Pay, please contact %1';
                    }
                    $this->messageManager->addErrorMessage(__(
                        $message,
                        'support@payplug.com'
                    ));
                } elseif ($environmentMode == Config::ENVIRONMENT_TEST) {
                    $groups['payplug_payments_apple_pay']['fields']['active']['value'] = 0;
                    $message = 'Apple Pay is unavailable in TEST mode. ' .
                        'Please switch your Payplug plugin to LIVE mode to activate it.';
                    $this->messageManager->addErrorMessage(__($message));
                }
            }
        }

        $this->request->setPostValue('groups', $groups);
    }

    /**
     * Handle Amex configuration
     *
     * @param array $groups
     */
    private function processAmexConfig($groups)
    {
        $this->processLiveOnlyMethod(
            $groups,
            'amex',
            __(
                'You don\'t have access to this feature yet. ' .
                'To activate American Express, please fill in the following form: ' .
                'https://support.payplug.com/hc/en-gb/requests/new?ticket_form_id=6331992459420'
            ),
            __(
                'Amex is unavailable in TEST mode. ' .
                'Please switch your Payplug plugin to LIVE mode to activate it.'
            )
        );
    }

    /**
     * Handle Satispay configuration
     *
     * @param array $groups
     */
    private function processSatispayConfig($groups)
    {
        $this->processLiveOnlyMethod(
            $groups,
            'satispay',
            __(
                'You don\'t have access to this feature yet. ' .
                'To activate Satispay, please fill in the following form: ' .
                'https://support.payplug.com/hc/en-gb/requests/new?ticket_form_id=8248599064860'
            ),
            __(
                'Satispay is unavailable in TEST mode. ' .
                'Please switch your Payplug plugin to LIVE mode to activate it.'
            )
        );
    }

    /**
     * Handle Ideal configuration
     *
     * @param array $groups
     */
    private function processIdealConfig($groups)
    {
        $this->processLiveOnlyMethod(
            $groups,
            'ideal',
            __(
                'You don\'t have access to this feature yet. ' .
                'To activate iDEAL, please fill in the following form: ' .
                'https://support.payplug.com/hc/en-gb/requests/new?ticket_form_id=8248663314844'
            ),
            __(
                'iDEAL is unavailable in TEST mode. ' .
                'Please switch your Payplug plugin to LIVE mode to activate it.'
            )
        );
    }

    /**
     * Handle Mybank configuration
     *
     * @param array $groups
     */
    private function processMybankConfig($groups)
    {
        $this->processLiveOnlyMethod(
            $groups,
            'mybank',
            __(
                'You don\'t have access to this feature yet. ' .
                'To activate MyBank, please fill in the following form: ' .
                'https://support.payplug.com/hc/en-gb/requests/new?ticket_form_id=8248631711516'
            ),
            __(
                'MyBank is unavailable in TEST mode. ' .
                'Please switch your Payplug plugin to LIVE mode to activate it.'
            )
        );
    }

    /**
     * Process method available only in LIVE mode
     *
     * @param array  $groups
     * @param string $method
     * @param Phrase $liveModeNoPermissionMessage
     * @param Phrase $testModeMessage
     */
    private function processLiveOnlyMethod($groups, $method, $liveModeNoPermissionMessage, $testModeMessage)
    {
        $groupCode = 'payplug_payments_' . $method;
        $fields = $groups[$groupCode]['fields'];

        $this->helper->initScopeData();
        $groups = $this->validatePayplugConnection($fields, $groups, $groupCode);

        if (!empty($fields['active']['value'])) {
            $environmentMode = $this->getConfig('environmentmode');

            if ($environmentMode == Config::ENVIRONMENT_LIVE) {
                $apiKey = $this->getConfig('live_api_key');
                if (empty($apiKey)) {
                    $this->messageManager->addErrorMessage(
                        __('We are not able to retrieve your account information. ' .
                            'Please go to section Sales > Payplug Payments to log in again.')
                    );
                } else {
                    $permissions = $this->getAccountPermissions($apiKey);

                    if (empty($permissions['can_use_' . $method])) {
                        $groups[$groupCode]['fields']['active']['value'] = 0;
                        $this->messageManager->addErrorMessage($liveModeNoPermissionMessage);
                    }
                }
            } else {
                $groups[$groupCode]['fields']['active']['value'] = 0;
                $this->messageManager->addErrorMessage($testModeMessage);
            }
        }

        $this->request->setPostValue('groups', $groups);
    }

    /**
     * Check if PayPlug account is connected before enabling PayPlug payment method
     *
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
     * Handle PayPlug configuration save on website level
     *
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
            if ($this->checkRequiredFields($fieldsRequiredForInit, $fields)) {
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
     * Check if all required fields are set
     *
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
                    if ( !isset($fields[$fieldCheck]['value']) && !isset($fields[$fieldCheck]['inherit'])) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Handle account init
     *
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
     * Handle live mode
     *
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
     * Get PayPlug configuration
     *
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
     * Save PayPlug configuration
     *
     * @param string $field
     * @param string $value
     */
    private function saveConfig($field, $value)
    {
        $this->helper->setConfigValue($field, $value);
    }

    /**
     * Save PayPlug configuration
     *
     * @param string $field
     * @param string $value
     */
    private function saveOneyConfig($field, $value)
    {
        $this->helper->setConfigValue($field, $value, ScopeInterface::SCOPE_STORE, null, Config::ONEY_CONFIG_PATH);
    }

    /**
     * Save PayPlug configuration
     *
     * @param string $field
     * @param string $value
     */
    private function saveOneyWithoutFeesConfig($field, $value)
    {
        $this->helper->setConfigValue($field, $value, ScopeInterface::SCOPE_STORE, null, Config::ONEY_WITHOUT_FEES_CONFIG_PATH);
    }

    /**
     * Connect to payplug account
     *
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
        $notEmptyValidator = new NotEmpty();
        if (!$notEmptyValidator->isValid($pwd)) {
            $error = true;
            $this->messageManager->addErrorMessage(__('Password field was empty.'));
        }

        $emailValidator = new EmailAddress();
        if (!$emailValidator->isValid($email)) {
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
     * Get PayPlug account permissions
     *
     * @param string $apiKey
     *
     * @return array
     */
    private function getAccountPermissions($apiKey)
    {
        if (!array_key_exists($apiKey, $this->permissions)) {
            $result = $this->login->getAccount($apiKey);
            if (!$result['status']) {
                $this->messageManager->addErrorMessage(__($result['message']));
                $this->permissions[$apiKey] = [];
            } else {
                $this->permissions[$apiKey] = $this->treatAccountResponse($result['answer']);
            }
        }

        return $this->permissions[$apiKey];
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
            'merchand_country' => $this->getConfig('merchand_country'),
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
                    $configuration['oney_min_amounts'] = $this->processAmounts(
                        $jsonAnswer['configuration']['oney']['min_amounts']
                    );
                    $configuration['raw_oney_min_amounts'] = intval($jsonAnswer['configuration']['oney']['min_amounts']['EUR'])/100;
                }
                if (!empty($jsonAnswer['configuration']['oney']['max_amounts'])) {
                    $configuration['oney_max_amounts'] = $this->processAmounts(
                        $jsonAnswer['configuration']['oney']['max_amounts']
                    );
                    $configuration['raw_oney_max_amounts'] = intval($jsonAnswer['configuration']['oney']['max_amounts']['EUR'])/100;
                }
            }
            if (!empty($jsonAnswer['country'])) {
                $configuration['merchand_country'] = $jsonAnswer['country'];
            }
        }

        $currencies = implode(';', $configuration['currencies']);
        $this->saveConfig('currencies', $currencies);
        $this->saveConfig('min_amounts', $configuration['min_amounts']);
        $this->saveConfig('max_amounts', $configuration['max_amounts']);
        $this->saveConfig('oney_countries', $configuration['oney_countries']);
        $this->saveConfig('oney_min_amounts', $configuration['oney_min_amounts']);
        $this->saveConfig('oney_max_amounts', $configuration['oney_max_amounts']);

        $this->saveOneyConfig('oney_min_threshold', $configuration['raw_oney_min_amounts']);
        $this->saveOneyConfig('oney_max_threshold', $configuration['raw_oney_max_amounts']);

        $this->saveOneyWithoutFeesConfig('oney_min_threshold', $configuration['raw_oney_min_amounts']);
        $this->saveOneyWithoutFeesConfig('oney_max_threshold', $configuration['raw_oney_max_amounts']);

        $this->saveConfig('company_id', $id);
        $this->saveConfig('merchand_country', $configuration['merchand_country']);

        // Harmonize bancontact/applepay/amex flags as a regular permission
        $jsonAnswer['permissions']['can_use_bancontact'] =
            $jsonAnswer['payment_methods']['bancontact']['enabled'] ?? false;
        $jsonAnswer['permissions']['can_use_apple_pay'] =
            $jsonAnswer['payment_methods']['apple_pay']['enabled'] ?? false;
        $jsonAnswer['permissions']['can_use_amex'] =
            $jsonAnswer['payment_methods']['american_express']['enabled'] ?? false;

        $permissions = [
            'use_live_mode',
            'can_save_cards',
            'can_create_installment_plan',
            'can_create_deferred_payment',
            'can_use_oney',
            'can_use_bancontact',
            'can_use_apple_pay',
            'can_use_amex',
            'can_use_integrated_payments',
        ];

        $pproMethods = [
            'satispay',
            'ideal',
            'mybank',
        ];
        foreach ($pproMethods as $method) {
            $jsonAnswer['permissions']['can_use_' . $method] =
                $jsonAnswer['payment_methods'][$method]['enabled'] ?? false;
            $permissions[] = 'can_use_' . $method;
            $this->saveConfig(
                $method . '_countries',
                json_encode($jsonAnswer['payment_methods'][$method]['allowed_countries'] ?? [])
            );
            $this->saveConfig(
                $method . '_min_amounts',
                $this->processAmounts(
                    $jsonAnswer['payment_methods'][$method]['min_amounts'] ?? []
                )
            );
            $this->saveConfig(
                $method . '_max_amounts',
                $this->processAmounts(
                    $jsonAnswer['payment_methods'][$method]['max_amounts'] ?? []
                )
            );
        }

        foreach ($permissions as $permission) {
            $this->saveConfig($permission, (int)$jsonAnswer['permissions'][$permission] ?? 0);
        }

        return $jsonAnswer['permissions'];
    }

    /**
     * Process min/max amounts
     *
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
