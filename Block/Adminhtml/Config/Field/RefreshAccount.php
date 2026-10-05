<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Block\Adminhtml\Config\Field;

use Exception;
use IntlDateFormatter;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Service\SynchronizeAccountData;

class RefreshAccount extends Field
{
    /**
     * @param Context $context
     * @param Config $helper
     * @param TimezoneInterface $timezone
     * @param Escaper $escaper
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly Config $helper,
        private readonly TimezoneInterface $timezone,
        private readonly Escaper $escaper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Retrieve element HTML markup
     *
     * @param AbstractElement $element
     * @return string
     * @throws LocalizedException
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $this->helper->initScopeData();

        /** @var Button $buttonBlock */
        $buttonBlock = $this->getForm()->getLayout()->createBlock(Button::class);
        $buttonBlock->setData([
            'id' => 'payplug_payments_refresh_account',
            'label' => __('Update account information'),
            'onclick' => sprintf(
                "deleteConfirm('%s', '%s', {data: {}})",
                $this->escaper->escapeJs((string) __(
                    'Unsaved changes on this page will be lost. ' .
                    'Do you want to update your Payplug account information now?'
                )),
                $this->escaper->escapeJs($this->getButtonUrl())
            ),
        ]);

        $lastUpdate = $this->escaper->escapeHtml(__('Last update: %1', $this->getLastUpdateLabel()));

        return $buttonBlock->toHtml() . '<p class="note"><span>' . $lastUpdate . '</span></p>';
    }

    /**
     * Render block HTML only when a Payplug account is connected
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $this->helper->initScopeData();

        if (!$this->isConnected()) {
            return '';
        }

        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        return parent::render($element);
    }

    /**
     * Return false to hide inherit checkbox
     *
     * @param mixed $element
     * @return false
     */
    protected function _isInheritCheckboxRequired($element): bool
    {
        return false;
    }

    /**
     * Is a Payplug account connected for the current scope or inherited from default scope
     *
     * @return bool
     */
    private function isConnected(): bool
    {
        if ($this->helper->isLegacyConnected() || $this->helper->isOauthConnected()) {
            return true;
        }

        if ($this->helper->getConfigScope() === ScopeConfigInterface::SCOPE_TYPE_DEFAULT) {
            return false;
        }

        $defaultScope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;

        return !empty($this->helper->getConfigValue('email', $defaultScope, 0))
            || !empty($this->helper->getConfigValue('email', $defaultScope, 0, Config::OAUTH_CONFIG_PATH));
    }

    /**
     * Get last account update date formatted in admin locale and timezone
     *
     * @return string
     */
    private function getLastUpdateLabel(): string
    {
        $lastUpdate = $this->helper->getConfigValue(SynchronizeAccountData::LAST_UPDATE_FIELD);

        if (empty($lastUpdate)) {
            return (string) __('Never');
        }

        try {
            return $this->timezone->formatDateTime((string) $lastUpdate, IntlDateFormatter::MEDIUM);
        } catch (Exception) {
            return (string) $lastUpdate;
        }
    }

    /**
     * Get button url according to current scope
     *
     * @return string
     */
    private function getButtonUrl(): string
    {
        $parameters = [];

        if ($this->helper->getConfigScope() === ScopeInterface::SCOPE_WEBSITES) {
            $parameters['website'] = $this->helper->getConfigScopeId();
        }

        return $this->getUrl('payplug_payments_admin/config/refreshAccount', $parameters);
    }
}
