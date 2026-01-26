<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Block\Adminhtml\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory as ConfigDataCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;

abstract class AbstractOauth2 extends Field
{
    /**
     * @param ConfigDataCollectionFactory $configDatacollection
     * @param Context $context
     * @param array $data
     * @param SecureHtmlRenderer|null $secureRenderer
     */
    public function __construct(
        private readonly ConfigDataCollectionFactory $configDatacollection,
        Context $context,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        parent::__construct($context, $data, $secureRenderer);
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
     * Is email set for current scope
     *
     * @return bool
     */
    protected function isEmailSetForCurrentScope(): bool
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
