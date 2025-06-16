<?php
declare(strict_types=1);

namespace Payplug\Payments\Block\Adminhtml\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;

class Oauth2LogoutBtn extends Field
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
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
            'onclick' => "setLocation('$url')",
        ];

        return $buttonBlock->setData($data)->toHtml();
    }

    public function render(AbstractElement $element): string
    {
        $websiteId = $this->getRequest()->getParam('website');

        $email = $this->scopeConfig->getValue(
            'payplug_payments/oauth2/email',
            $websiteId ? StoreScopeInterface::SCOPE_WEBSITES : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $websiteId ?: 0
        );

        if (!$email) {
            return '';
        }

        return parent::render($element);
    }
}
