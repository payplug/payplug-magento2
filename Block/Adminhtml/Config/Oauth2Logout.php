<?php

declare(strict_types=1);

namespace Payplug\Payments\Block\Adminhtml\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory as ConfigDataCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Service\GetOauth2AccessTokenData;
use Payplug\Payments\Service\GetOauth2ClientData;

class Oauth2Logout extends AbstractOauth2
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly GetOauth2AccessTokenData $getOauth2AccessTokenData,
        private readonly State $appState,
        private readonly TimezoneInterface $timezone,
        private readonly GetOauth2ClientData $getOauth2ClientData,
        ConfigDataCollectionFactory $configDatacollection,
        Context $context,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        parent::__construct(
            $configDatacollection,
            $context,
            $data,
            $secureRenderer
        );
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
            'You are currently authenticated with email <strong>%1</strong> (%2)',
            $this->getEmailValue(),
            $websiteId && $this->isEmailSetForCurrentScope() ? __('Website') : __('Default')
        );

        $info = <<<HTML
<div class="message message-success">$statusLabel</div>
HTML;

        if (!$this->isEmailSetForCurrentScope()) {
            return $info;
        }

        if ($this->isDeveloperMode()) {
            $testClientData = $this->getOauth2ClientData->execute(Config::ENVIRONMENT_TEST, (int)$websiteId);
            $liveClientData = $this->getOauth2ClientData->execute(Config::ENVIRONMENT_LIVE, (int)$websiteId);
            $accessTokenData = $this->getAccessTokenData();
            $expirationLabel = __('Current access token expiration date');
            $testClientValueLabel = __('TEST client credentials available');
            $liveClientValueLabel = __('LIVE client credentials available');

            if (!empty($accessTokenData['expires_date'])) {
                $expirationDate = $this->timezone->date($accessTokenData['expires_date'])->format('Y-m-d H:i:s');
            } else {
                $expirationDate = __('N/A');
            }

            $testClientCredentials = $testClientData ? __('Yes') : __('No');
            $liveClientCredentials = $liveClientData ? __('Yes') : __('No');

            $info .= <<<HTML
<div class="message info">$testClientValueLabel : $testClientCredentials</code></div>
<div class="message info">$liveClientValueLabel : $liveClientCredentials</code></div>
<div class="message info">$expirationLabel : $expirationDate</div>
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

    private function isDeveloperMode(): bool
    {
        return $this->appState->getMode() === State::MODE_DEVELOPER;
    }
}
