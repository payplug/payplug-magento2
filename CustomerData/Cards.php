<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\CustomerData;

use Magento\Checkout\Model\Session as CustomerSession;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Payplug\Payments\Api\Data\PaymentTokenInterface;
use Payplug\Payments\Helper\Card;
use Payplug\Payments\Helper\Config as PayplugConfig;
use Payplug\Payments\Service\GetHostedFieldsSavedCards;
use Throwable;

class Cards implements SectionSourceInterface
{
    /**
     * @param CustomerSession $checkoutSession
     * @param PayplugConfig $payplugConfig
     * @param Card $helper
     * @param GetHostedFieldsSavedCards $getHostedFieldsSavedCards
     */
    public function __construct(
        private readonly CustomerSession $checkoutSession,
        private readonly PayplugConfig $payplugConfig,
        private readonly Card $helper,
        private readonly GetHostedFieldsSavedCards $getHostedFieldsSavedCards
    ) {
    }

    /**
     * Get cards section data
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getSectionData(): array
    {
        $customerId = (int) $this->checkoutSession->getQuote()->getCustomer()->getId();
        $websiteId = (int) $this->checkoutSession->getQuote()->getStore()->getWebsiteId();

        if ($this->payplugConfig->isHostedFieldsActive($websiteId) === true) {
            $cards = $this->getHostedFieldsCardsResult($customerId);
        } else {
            $cards = $this->getPayplugRetailSavedCards($customerId);
        }

        if (!empty($cards)) {
            $cards[] = [
                'id' => '',
            ];
        }

        return ['cards' => $cards];
    }

    /**
     * Get Payplug Retail saved cards
     *
     * @param int $customerId
     * @return array
     */
    private function getPayplugRetailSavedCards(int $customerId): array
    {
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

        return $cards;
    }

    /**
     * Get Hosted Fields saved cards
     *
     * @param int $customerId
     * @return array
     */
    private function getHostedFieldsCardsResult(int $customerId): array
    {
        try {
            $storeId = (int) $this->checkoutSession->getQuote()->getStoreId();
            $customerCards = $this->getHostedFieldsSavedCards->execute($customerId, $storeId);
        } catch (Throwable) {
            return [];
        }

        $cards = [];

        foreach ($customerCards as $customerCard) {
            $token = $customerCard[GetHostedFieldsSavedCards::TOKEN_OBJECT_KEY];
            $tokenDetails = $customerCard[GetHostedFieldsSavedCards::TOKEN_DETAILS_KEY];

            $brand = $tokenDetails[PaymentTokenInterface::DETAIL_BRAND] ?? null;
            $last4 = $tokenDetails[PaymentTokenInterface::MASKED_CC] ?? null;
            $expDate = $tokenDetails[PaymentTokenInterface::EXP_DATE] ?? null;

            if (empty($brand) || empty($last4) || empty($expDate)) {
                continue;
            }

            $cards[] = [
                'id' => $token->getPublicHash(),
                'brand' => $brand,
                'last4' => $last4,
                'exp_date' => $expDate,
            ];
        }

        return $cards;
    }
}
