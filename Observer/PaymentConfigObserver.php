<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Observer;

use Laminas\Stdlib\Parameters;
use Laminas\Validator\EmailAddress;
use Laminas\Validator\NotEmpty;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Phrase;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Model\Api\Login;
use Payplug\Payments\Service\GetOauth2ClientData;

class PaymentConfigObserver implements ObserverInterface
{
    /**
     * @var bool
     */
    private bool $payplugConfigConnected;
    /**
     * @var bool
     */
    private bool $payplugConfigVerified;
    /**
     * @var string
     */
    private string $testApiKey;
    /**
     * @var string
     */
    private string $liveApiKey;
    /**
     * @var array
     */
    private array $permissions = [];

    /**
     * @param Http $request
     * @param Login $login
     * @param Config $helper
     * @param ManagerInterface $messageManager
     * @param StoreManagerInterface $storeManager
     * @param GetOauth2ClientData $getOauth2ClientData
     */
    public function __construct(
        private readonly Http $request,
        private readonly Login $login,
        private readonly Config $helper,
        private readonly ManagerInterface $messageManager,
        private readonly StoreManagerInterface $storeManager,
        private readonly GetOauth2ClientData $getOauth2ClientData
    ) {
    }

    /**
     * Handle PayPlug configuration save
     *
     * @param EventObserver $observer
     */
    public function execute(EventObserver $observer): void
    {
        $postParams = $this->request->getPost();

        if ($postParams instanceof Parameters) {
            $postParams = $postParams->toArray();
        }

        if (!isset($postParams['groups'], $postParams['config_state'])) {
            return;
        }

        $groups = $postParams['groups'];
        $sections = $postParams['config_state'];

        if (isset($sections['payplug_payments_general']) && isset($groups['general']['fields'])) {
            $isLegacy = !$this->helper->isOauthConnected(
                ScopeInterface::SCOPE_WEBSITE,
                $this->getCurrentWebsite()
            );

            if ($isLegacy) {
                $this->processGeneralConfig($groups);
            } else {
                $this->processOauthGeneralConfig($groups);
            }

            $this->setPostAndParamGroups($groups);

            return;
        }

        if ($this->canProcessSection($postParams, 'payplug_payments_standard')) {
            $this->processStandardConfig($groups);
        }

        if ($this->canProcessSection($postParams, 'payplug_payments_installment_plan')) {
            $this->processInstallmentPlanConfig($groups);
        }

        if ($this->canProcessSection($postParams, 'payplug_payments_ondemand')) {
            $this->processOndemandConfig($groups);
        }

        if ($this->canProcessSection($postParams, 'payplug_payments_oney') ||
            $this->canProcessSection($postParams, 'payplug_payments_oney_without_fees')
        ) {
            $this->processOneyConfig($postParams['groups']);
        }

        if ($this->canProcessSection($postParams, 'payplug_payments_bancontact')) {
            $this->processBancontactConfig($groups);
        }

        if ($this->canProcessSection($postParams, 'payplug_payments_apple_pay')) {
            $this->processApplePayConfig($groups);
        }

        if ($this->canProcessSection($postParams, 'payplug_payments_amex')) {
            $this->processAmexConfig($groups);
        }

        if ($this->canProcessSection($postParams, 'payplug_payments_satispay')) {
            $this->processSatispayConfig($groups);
        }

        if ($this->canProcessSection($postParams, 'payplug_payments_ideal')) {
            $this->processIdealConfig($groups);
        }

        if ($this->canProcessSection($postParams, 'payplug_payments_mybank')) {
            $this->processMybankConfig($groups);
        }

        $this->setPostAndParamGroups($groups);
    }

    /**
     * Check if posted data contains section info
     *
     * @param array $postParams
     * @param string $sectionCode
     * @return bool
     */
    private function canProcessSection(array $postParams, string $sectionCode): bool
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
     * @return void
     */
    private function processGeneralConfig(array &$groups): void
    {
        $authFields = $fields = $groups['auth']['fields'];

        if (empty($fields['email']) && !empty($authFields['email'])) {
            $fields['email'] = $authFields['email'];
        }

        if (empty($fields['pwd']) && !empty($authFields['pwd'])) {
            $fields['pwd'] = $authFields['pwd'];
        }

        $this->helper->initScopeData();

        $this->payplugConfigConnected = $this->helper->isLegacyConnected();
        $this->payplugConfigVerified = (bool)$this->getConfig('verified');

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
            unset($groups['auth']['fields']['pwd']);
            unset($groups['auth']['fields']['email']);
            $this->saveConfig('connected', 0);
        }
        if (!$this->payplugConfigVerified && $isLive) {
            $groups['general']['fields']['environmentmode']['value'] = Config::ENVIRONMENT_TEST;
            $this->saveConfig('verified', 0);
            $this->messageManager->addErrorMessage(__('You are able to perform only TEST transactions.'));
        }

        $this->enforceIntegratedPaymentPermissions($groups, $fields);
    }

    /**
     * Enforce integrated payment permissions
     *
     * @param array $groups
     * @param array $fields
     * @return void
     */
    private function enforceIntegratedPaymentPermissions(array &$groups, array $fields): void
    {
        $apiKey = $this->getCurrentApiKey();

        if (!$apiKey && $this->payplugConfigConnected) {
            $apiKey = $this->testApiKey ?: $this->getConfig('test_api_key');
        }

        if (!$apiKey) {
            return;
        }

        $permissions = $this->getAccountPermissions($apiKey);
        $paymentValue = $fields['payment_page']['value']
            ?? $fields['payment_page']['inherit']
            ?? null;

        if ($paymentValue === Config::PAYMENT_PAGE_INTEGRATED && empty($permissions['can_use_integrated_payments'])) {

            $paymentPage = $this->getConfig('payment_page');
            if (!$paymentPage || $paymentPage === Config::PAYMENT_PAGE_INTEGRATED) {
                $paymentPage = Config::PAYMENT_PAGE_REDIRECT;
            }
            $groups['general']['fields']['payment_page']['value'] = $paymentPage;

            $this->messageManager->addErrorMessage(
                __(
                    'You do not have access to this feature yet. ' .
                    'To activate it, please fill in the following form: ' .
                    'https://support.payplug.com/hc/en-gb/requests/new?ticket_form_id=8138934372636'
                )
            );
        }
    }

    /**
     * Returns the correct secret (legacy test/live OR OAuth2 access token)
     */
    private function getCurrentApiKey(): ?string
    {
        $env = $this->getConfig('environmentmode');
        $isTest = ($env === Config::ENVIRONMENT_TEST);

        return $this->helper->getApiKey($isTest);
    }

    /**
     * Same idea as legacy processGeneralConfig() but without e-mail/pwd logic.
     *
     * @param array $groups
     * @return void
     */
    private function processOauthGeneralConfig(array &$groups): void
    {
        $fields = $groups['general']['fields'] ?? [];

        $this->helper->initScopeData();

        $this->payplugConfigConnected = $this->helper->isOauthConnected();
        $this->payplugConfigVerified = true;

        if (isset($fields['environmentmode']['value'])
            && $fields['environmentmode']['value'] == Config::ENVIRONMENT_LIVE
            && $this->getOauth2ClientData->execute(Config::ENVIRONMENT_LIVE, $this->getCurrentWebsite()) === null
        ) {
            $groups['general']['fields']['environmentmode']['value'] = Config::ENVIRONMENT_TEST;
            $this->saveConfig('environmentmode', Config::ENVIRONMENT_TEST);
            $this->messageManager->addErrorMessage(__('You are able to perform only TEST transactions.'));
            $this->messageManager->addErrorMessage(
                __('Once your account is verified, you will need to logout, then login to refresh permissions')
            );
        }

        $this->enforceIntegratedPaymentPermissions($groups, $fields);
    }

    /**
     * Set Post and Param groups
     *
     * @param array $groups
     * @return void
     */
    public function setPostAndParamGroups(array $groups): void
    {
        //Old method for 2.3 and older
        $this->request->setPostValue('groups', $groups);
        //New method for 2.4+
        $this->request->setParam('groups', $groups);
    }

    /**
     * Handle Standard configuration
     *
     * @param array $groups
     * @return void
     */
    private function processStandardConfig(array &$groups): void
    {
        $fields = $groups['payplug_payments_standard']['fields'];

        $this->helper->initScopeData();
        $this->validatePayplugConnection($fields, $groups, 'payplug_payments_standard');

        if (!empty($fields['one_click']['value'])) {
            $environmentMode = $this->getConfig('environmentmode');

            $apiKey = $this->getCurrentApiKey();

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
    }

    /**
     * Handle InstallmentPlan configuration
     *
     * @param array $groups
     * @return void
     */
    private function processInstallmentPlanConfig(array &$groups): void
    {
        $fields = $groups['payplug_payments_installment_plan']['fields'];

        $this->helper->initScopeData();
        $this->validatePayplugConnection($fields, $groups, 'payplug_payments_installment_plan');

        if (!empty($fields['active']['value'])) {
            $environmentMode = $this->getConfig('environmentmode');

            $apiKey = $this->getCurrentApiKey();

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
    }

    /**
     * Handle Ondemand configuration
     *
     * @param array $groups
     * @return void
     */
    private function processOndemandConfig(array &$groups): void
    {
        $fields = $groups['payplug_payments_ondemand']['fields'];

        $this->helper->initScopeData();
        $this->validatePayplugConnection($fields, $groups, 'payplug_payments_ondemand');
    }

    /**
     * Handle Oney configuration
     *
     * @param array $groups
     * @return void
     * @throws NoSuchEntityException
     */
    private function processOneyConfig(array &$groups): void
    {
        $oneyGroups = [
            'payplug_payments_oney' => [],
            'payplug_payments_oney_without_fees' => [],
        ];

        $bothActive = true;
        foreach (array_keys($oneyGroups) as $oney) {
            $fields = $groups[$oney]['fields'];

            $this->helper->initScopeData();
            $this->validatePayplugConnection($fields, $groups, $oney);

            $oneyGroups[$oney]['current'] = $this->getConfig('active', sprintf('payment/%s/', $oney));

            $isActive = false;
            if (!empty($fields['active']['value'])) {
                $environmentMode = $this->getConfig('environmentmode');

                $apiKey = $this->getCurrentApiKey();

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
                            __($errorMessage, 'https://portal.payplug.com/#/configuration/oney')
                        );
                    } else {
                        $isActive = true;
                    }
                }

                // If customer loggedin && have permissions
                if ($isActive) {
                    $storeId = (int)$this->storeManager->getStore()->getId();
                    $currency = $this->storeManager->getStore()->getCurrentCurrencyCode();

                    $minAmountsConfig = $this->helper->getConfigValue(
                        'oney_min_amounts',
                        ScopeInterface::SCOPE_STORE,
                        null,
                        Config::ONEY_CONFIG_PATH
                    ) ?
                        $this->helper->getConfigValue(
                            'oney_min_amounts',
                            ScopeInterface::SCOPE_STORE,
                            null,
                            Config::ONEY_CONFIG_PATH
                        ) :
                        $this->helper->getConfigValue(
                            'oney_min_amounts',
                            ScopeInterface::SCOPE_STORE,
                            null,
                            Config::CONFIG_PATH
                        );

                    $maxAmountsConfig = $this->helper->getConfigValue(
                        'oney_max_amounts',
                        ScopeInterface::SCOPE_STORE,
                        null,
                        Config::ONEY_CONFIG_PATH
                    ) ?
                        $this->helper->getConfigValue(
                            'oney_max_amounts',
                            ScopeInterface::SCOPE_STORE,
                            null,
                            Config::ONEY_CONFIG_PATH
                        ) :
                        $this->helper->getConfigValue(
                            'oney_max_amounts',
                            ScopeInterface::SCOPE_STORE,
                            null,
                            Config::CONFIG_PATH
                        );

                    $oneyDefaultThresholds = $this->helper->getAmountsByCurrency(
                        $currency,
                        null,
                        Config::CONFIG_PATH,
                        'oney_'
                    );

                    if (!$this->validateThresholdValues($fields, $oneyDefaultThresholds)) {

                        $this->messageManager->addErrorMessage(
                            __('The value is not within the specified range.')
                        );
                    }

                    // Website scope value
                    if (isset($groups[$oney]['fields']['oney_min_threshold']['value'])) {
                        $min = $groups[$oney]['fields']['oney_min_threshold']['value'];
                        $max = $groups[$oney]['fields']['oney_max_threshold']['value'];
                    } else {
                        // Inherit value
                        $min = $groups[$oney]['fields']['oney_min_threshold']['inherit'];
                        $max = $groups[$oney]['fields']['oney_max_threshold']['inherit'];
                    }

                    // Save thresholds on the same format as general/oney_max_amount
                    $this->saveOneyConfig(
                        'oney_min_amounts',
                        preg_replace(
                            "/(?<=:).*$/i",
                            (string)((int)$min * 100),
                            (string)$minAmountsConfig
                        )
                    );

                    $this->saveOneyConfig('oney_max_amounts', preg_replace(
                        "/(?<=:).*$/i",
                        (string)((int)$max * 100),
                        (string)$maxAmountsConfig
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
    }

    /**
     * Validate threshold values
     *
     * @param array $fields
     * @param bool|array $oneyThresholds
     * @return bool
     */
    private function validateThresholdValues(array $fields, bool|array $oneyThresholds): bool
    {
        if (isset($fields['oney_min_threshold']["value"])) {
            $minThreshold = (int) $fields['oney_min_threshold']['value'];
            $maxThreshold = (int) $fields['oney_max_threshold']['value'];
        } elseif (isset($fields['oney_min_threshold']["inherit"])) {
            // Website scope has on inheriting
            $minThreshold = (int) $fields['oney_min_threshold']['inherit'];
            $maxThreshold = (int) $fields['oney_max_threshold']['inherit'];
        } else {
            return false;
        }

        if ($oneyThresholds === false) {
            return false;
        }

        if ($minThreshold >= $maxThreshold) {
            return false;
        }

        if ($minThreshold < ($oneyThresholds["min_amount"] / 100)) {
            return false;
        }

        if ($maxThreshold > ($oneyThresholds["max_amount"] / 100)) {
            return false;
        }

        return true;
    }

    /**
     * Handle Bancontact configuration
     *
     * @param array $groups
     * @return void
     */
    private function processBancontactConfig(array &$groups): void
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
     * @return void
     */
    private function processApplePayConfig(array &$groups): void
    {
        $fields = $groups['payplug_payments_apple_pay']['fields'];

        $this->helper->initScopeData();
        $this->validatePayplugConnection($fields, $groups, 'payplug_payments_apple_pay');

        if (!empty($fields['active']['value'])) {
            $environmentMode = $this->getConfig('environmentmode');

            $apiKey = $this->getCurrentApiKey();
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
    }

    /**
     * Handle Amex configuration
     *
     * @param array $groups
     * @return void
     */
    private function processAmexConfig(array &$groups): void
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
     * @return void
     */
    private function processSatispayConfig(array &$groups): void
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
     * @return void
     */
    private function processIdealConfig(array &$groups): void
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
     * @return void
     */
    private function processMybankConfig(array &$groups): void
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
     * Is PayPlug account connected
     *
     * @return bool
     */
    private function isPayplugConnected(): bool
    {
        return $this->helper->isLegacyConnected() || $this->helper->isOauthConnected();
    }

    /**
     * Process method available only in LIVE mode
     *
     * @param array $groups
     * @param string $method
     * @param Phrase|string $liveModeNoPermissionMessage
     * @param Phrase|string $testModeMessage
     * @return void
     */
    private function processLiveOnlyMethod(
        array &$groups,
        string $method,
        Phrase|string $liveModeNoPermissionMessage,
        Phrase|string $testModeMessage
    ): void {
        $groupCode = 'payplug_payments_' . $method;
        $fields = $groups[$groupCode]['fields'];

        $this->helper->initScopeData();
        $this->validatePayplugConnection($fields, $groups, $groupCode);

        if (empty($fields['active']['value'])) {
            return;
        }

        $environmentMode = $this->getConfig('environmentmode');
        if ($environmentMode !== Config::ENVIRONMENT_LIVE) {
            $groups[$groupCode]['fields']['active']['value'] = 0;
            $this->messageManager->addErrorMessage($testModeMessage);
            return;
        }

        $apiKey = $this->getCurrentApiKey();
        if (!$apiKey) {
            $this->messageManager->addErrorMessage(
                __(
                    'We are not able to retrieve your account information. ' .
                    'Please go to section Sales > Payplug Payments to log in again.'
                )
            );
            $groups[$groupCode]['fields']['active']['value'] = 0;
            return;
        }

        $permissions = $this->getAccountPermissions($apiKey);
        if (empty($permissions['can_use_' . $method])) {
            $groups[$groupCode]['fields']['active']['value'] = 0;
            $this->messageManager->addErrorMessage($liveModeNoPermissionMessage);
        }
    }

    /**
     * Check if PayPlug account is connected before enabling PayPlug payment method
     *
     * @param array $fields
     * @param array $groups
     * @param string $fieldGroup
     * @return void
     */
    private function validatePayplugConnection(array $fields, array &$groups, string $fieldGroup): void
    {
        if (!empty($fields['active']['value']) && !$this->isPayplugConnected()) {
            $this->messageManager->addErrorMessage(
                __('You are not connected to a payplug account. ' .
                    'Please go to section Sales > Payplug Payments to log in.')
            );

            $groups[$fieldGroup]['fields']['active']['value'] = 0;
        }
    }

    /**
     * Handle PayPlug configuration save on website level
     *
     * @param array $groups
     * @param array $fields
     * @return void
     */
    private function checkWebsiteScopeData(array &$groups, array &$fields): void
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
     * @return bool
     */
    private function checkRequiredFields(array $fieldsRequiredForInit, array $fields): bool
    {
        foreach ($fieldsRequiredForInit as $field) {
            if (isset($fields[$field]['value'])) {
                foreach ($fieldsRequiredForInit as $fieldCheck) {
                    if (!isset($fields[$fieldCheck]['value']) && !isset($fields[$fieldCheck]['inherit'])) {
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
     * @param array $fields
     * @return void
     */
    private function processInit(?string $pwd, array $fields): void
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
     * @return void
     */
    private function processLive(?string $pwd): void
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
     * Get config
     *
     * @param string $field
     * @param string $path
     * @return mixed
     */
    private function getConfig(string $field, string $path = Config::CONFIG_PATH): mixed
    {
        return $this->helper->getConfigValue($field, ScopeInterface::SCOPE_STORE, null, $path);
    }

    /**
     * Save config
     *
     * @param string $field
     * @param mixed $value
     * @return void
     */
    private function saveConfig(string $field, mixed $value): void
    {
        $this->helper->setConfigValue($field, (string)$value);
    }

    /**
     * Save Oney Config
     *
     * @param string $field
     * @param mixed $value
     * @return void
     */
    private function saveOneyConfig(string $field, mixed $value): void
    {
        $this->helper->setConfigValue(
            $field,
            (string)$value,
            ScopeInterface::SCOPE_STORE,
            null,
            Config::ONEY_CONFIG_PATH
        );
    }

    /**
     * Save Oney Without Fees Config
     *
     * @param string $field
     * @param mixed $value
     * @return void
     */
    private function saveOneyWithoutFeesConfig(string $field, mixed $value): void
    {
        $this->helper->setConfigValue(
            $field,
            (string)$value,
            ScopeInterface::SCOPE_STORE,
            null,
            Config::ONEY_WITHOUT_FEES_CONFIG_PATH
        );
    }

    /**
     * Connect to payplug account. Handle flags for account connection, verification
     *
     * @param string $email
     * @param string $pwd
     * @param bool $canChangeConfigConnected
     * @return bool
     */
    private function payplugLogin(string $email, string $pwd, bool $canChangeConfigConnected = false): bool
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
     * @return array
     */
    private function getAccountPermissions(string $apiKey): array
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
     * @return array|null
     */
    private function treatAccountResponse(mixed $jsonAnswer): ?array
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
                    $minAmounts = (int) $jsonAnswer['configuration']['oney']['min_amounts']['EUR'];
                    $configuration['raw_oney_min_amounts'] = $minAmounts / 100;
                }
                if (!empty($jsonAnswer['configuration']['oney']['max_amounts'])) {
                    $configuration['oney_max_amounts'] = $this->processAmounts(
                        $jsonAnswer['configuration']['oney']['max_amounts']
                    );
                    $maxAmount = (int) $jsonAnswer['configuration']['oney']['max_amounts']['EUR'];
                    $configuration['raw_oney_max_amounts'] = $maxAmount / 100;
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
            $jsonAnswer['permissions']['can_use_' . $method] = $jsonAnswer['payment_methods'][$method]['enabled']
                ?? false;
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
     * @param array|null $amounts
     * @return string
     */
    private function processAmounts(?array $amounts): string
    {
        $configuration = '';
        foreach ($amounts as $key => $value) {
            if ($configuration !== '') {
                $configuration .= ';';
            }
            $configuration .= $key . ':' . $value;
        }

        return $configuration;
    }

    /**
     * Get Current Website
     *
     * @return int
     */
    private function getCurrentWebsite(): int
    {
        return (int)$this->request->getParam('website');
    }
}
