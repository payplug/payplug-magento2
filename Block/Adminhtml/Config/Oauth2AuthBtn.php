<?php
declare(strict_types=1);

namespace Payplug\Payments\Block\Adminhtml\Config;

use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Oauth2AuthBtn extends Field
{
    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function _getElementHtml(AbstractElement $element): string
    {
        /** @var Button $buttonBlock  */
        $buttonBlock = $this->getForm()->getLayout()->createBlock(Button::class);
        $data = [
            'label' => __('Login'),
            'onclick' => "setLocation('" . $this->getUrl('payplug_payments_admin/config/oauth2Auth') . "')",
        ];

        return $buttonBlock->setData($data)->toHtml();
    }
}
