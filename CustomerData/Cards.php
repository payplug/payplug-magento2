<?php

namespace Payplug\Payments\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Payplug\Payments\Helper\Card;

class Cards implements SectionSourceInterface
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var Card
     */
    private $helper;

    /**
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param Card                            $helper
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        Card $helper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->helper = $helper;
    }

    /**
     * @return array
     */
    public function getSectionData()
    {
        $customerId = $this->checkoutSession->getQuote()->getCustomer()->getId();
        $customerCards = $this->helper->getCardsByCustomer($customerId);

        $cards = [];
        foreach ($customerCards as $card) {
            $cards[] = [
                'id' => $card->getCustomerCardId(),
                'brand' => $card->getBrand(),
                'last4' => $card->getLastFour(),
                'exp_date' => $this->helper->getFormattedExpDate($card->getExpDate()),
            ];
        }
        if (!empty($cards)) {
            $cards[] = [
                'id' => '',
            ];
        }

        return ['cards' => $cards];
    }
}
