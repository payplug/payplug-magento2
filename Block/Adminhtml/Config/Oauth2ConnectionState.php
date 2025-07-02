<?php

declare(strict_types=1);

namespace Payplug\Payments\Block\Adminhtml\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory as ConfigDataCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Payplug\Payments\Service\GetOauth2AccessTokenData;

class Oauth2ConnectionState extends Field
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ConfigDataCollectionFactory $configDatacollection,
        private readonly GetOauth2AccessTokenData $getOauth2AccessTokenData,
        private readonly TimezoneInterface $timezone,
        private readonly State $appState,
        Context $context,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        parent::__construct($context, $data, $secureRenderer);
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function _getElementHtml(AbstractElement $element): string
    {
        /** @var Button $buttonBlock  */
        $buttonBlock = $this->getForm()->getLayout()->createBlock(Button::class);
        $websiteId = $this->getRequest()->getParam('website');
        $url = $this->getUrl('payplug_payments_admin/config/oauth2Logout', ['website' => $websiteId]);

        $data = [
            'label' => __('Logout'),
            'onclick' => "setLocation('$url')"
        ];

        $statusLabel = __(
            'Your are currently authenticated with email <strong>%1</strong> (%2)',
            $this->getEmailValue(),
            $websiteId && $this->isEmailSetForCurrentScope() ? __('Website') : __('Default')
        );

        $info = <<<HTML
<div class="message message-success">{$statusLabel}</div>
HTML;

        if (!$this->isEmailSetForCurrentScope()) {
            return $info;
        }

        if ($this->isDeveloperMode()) {
            $accessTokenData = $this->getAccessTokenData();
            $expirationDate = $this->timezone->date($accessTokenData['expires_date'])->format('Y-m-d H:i:s');
            $expirationLabel = __('Current access token expiration date');
            $tokenValueLabel = __('Current access token value');

            $info .= <<<HTML
<div class="message info">{$expirationLabel} : {$expirationDate}</div>
<div class="message info">{$tokenValueLabel} : <code style="overflow-wrap: anywhere;">{$accessTokenData['access_token']}</code></div>
HTML;
        }

        return $info . '<br>' . $buttonBlock->setData($data)->toHtml();
    }

    public function render(AbstractElement $element): string
    {
        if (!$this->getEmailValue()) {
            return '';
        }

        return parent::render($element);
    }

    protected function _isInheritCheckboxRequired($element)
    {
        return false;
    }

    public function isEmailSetForCurrentScope(): bool
    {
        $websiteId = $this->getCurrentWebsite();

        $scope = $websiteId ? StoreScopeInterface::SCOPE_WEBSITES : ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $scopeId = $websiteId ?: 0;

        $collection = $this->configDatacollection->create();
        $collection->addFieldToFilter('path', 'payplug_payments/oauth2/email')
            ->addFieldToFilter('scope', $scope)
            ->addFieldToFilter('scope_id', $scopeId);

        return (bool)$collection->getSize();
    }

    private function getEmailValue(): ?string
    {

        $websiteId = $this->getCurrentWebsite();

        return $this->scopeConfig->getValue(
            'payplug_payments/oauth2/email',
            $websiteId ? StoreScopeInterface::SCOPE_WEBSITES : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $websiteId ?: 0
        );
    }

    private function getAccessTokenData(): ?array
    {
        try {
            return $this->getOauth2AccessTokenData->execute($this->getCurrentWebsite());
        } catch (LocalizedException) {
            return null;
        }
    }

    private function getCurrentWebsite(): ?int
    {
        return $this->getRequest()->getParam('website') ?: null;
    }

    public function isDeveloperMode(): bool
    {
        return $this->appState->getMode() === State::MODE_DEVELOPER;
    }
}
