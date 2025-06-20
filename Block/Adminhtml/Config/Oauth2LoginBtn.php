<?php

declare(strict_types=1);

namespace Payplug\Payments\Block\Adminhtml\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory as ConfigDataCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;

class Oauth2LoginBtn extends Field
{
    public function __construct(
        private readonly ConfigDataCollectionFactory $configDatacollection,
        Context $context,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        parent::__construct($context, $data, $secureRenderer);
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function _getElementHtml(AbstractElement $element): string
    {
        /** @var Button $buttonBlock */
        $buttonBlock = $this->getForm()->getLayout()->createBlock(Button::class);
        $websiteId = $this->getRequest()->getParam('website');
        $url = $this->getUrl('payplug_payments_admin/config/oauth2Login', ['website' => $websiteId]);

        $data = [
            'label' => $websiteId ? __('Connect to Payplug for this website') : __('Connect to Payplug'),
            'onclick' => "setLocation('$url')",
            'class' => 'action-primary'
        ];

        return $buttonBlock->setData($data)->toHtml();
    }

    public function render(AbstractElement $element): string
    {
        if ($this->isEmailSetForCurrentScope()) {
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
        $websiteId = $this->getRequest()->getParam('website');

        $scope = $websiteId ? StoreScopeInterface::SCOPE_WEBSITES : ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $scopeId = $websiteId ?: 0;

        $collection = $this->configDatacollection->create();
        $collection->addFieldToFilter('path', 'payplug_payments/oauth2/email')
            ->addFieldToFilter('scope', $scope)
            ->addFieldToFilter('scope_id', $scopeId);

        return (bool)$collection->getSize();
    }
}
