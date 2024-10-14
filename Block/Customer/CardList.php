<?php

declare(strict_types=1);

namespace Payplug\Payments\Block\Customer;

use Magento\Customer\Model\Session;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Payplug\Payments\Helper\Card;
use Payplug\Payments\Model\Customer\Card as CustomerCard;

class CardList extends Template
{
    public function __construct(
        Context $context,
        private Session $customerSession,
        private Card $helper,
        private FormKey $formKey,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get customer saved PayPlug cards
     *
     * @return CustomerCard[]
     */
    public function getPayplugCards(): array
    {
        return $this->helper->getCardsByCustomer($this->customerSession->getCustomer()->getId(), true);
    }

    /**
     * Format card expiration date
     */
    public function getFormattedExpDate(string $date): string
    {
        return $this->helper->getFormattedExpDate($date);
    }

    /**
     * Build delete card url
     */
    public function getDeleteCardUrl(int $customerCardId): string
    {
        return $this->_urlBuilder->getUrl('payplug_payments/customer/cardDelete', [
            'customer_card_id' => $customerCardId,
            'form_key' => $this->formKey->getFormKey() ?: ''
        ]);
    }
}
