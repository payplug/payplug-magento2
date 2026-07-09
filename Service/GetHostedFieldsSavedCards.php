<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Payplug\Payments\Gateway\Config\Standard;
use Payplug\Payments\Helper\Config as PayplugConfig;
use Throwable;

class GetHostedFieldsSavedCards
{
    public const TOKEN_OBJECT_KEY = 'token';
    public const TOKEN_DETAILS_KEY = 'details';

    /**
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param SerializerInterface $serializer
     * @param PayplugConfig $payplugConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly PaymentTokenManagementInterface $paymentTokenManagement,
        private readonly SerializerInterface $serializer,
        private readonly PayplugConfig $payplugConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Get Hosted Fields saved cards
     *
     * @param int $customerId
     * @param int $storeId
     * @return array
     * @throws NoSuchEntityException
     */
    public function execute(int $customerId, int $storeId): array
    {
        $websiteId = (int) $this->storeManager->getStore($storeId)->getWebsiteId();
        $hostedFieldsTokens = [];
        $currentIdentifier = $this->payplugConfig->getHostedFieldsIdentifier($websiteId);

        $tokens = $this->paymentTokenManagement->getVisibleAvailableTokens($customerId);

        foreach ($tokens as $token) {
            $tokenDetailsSerialized = $token->getTokenDetails();

            try {
                $tokenDetails = $this->serializer->unserialize($tokenDetailsSerialized);
                $hostedFieldsIdentifier = isset($tokenDetails['hosted_fields_identifier'])
                    ? (string) $tokenDetails['hosted_fields_identifier'] : null;
            } catch (Throwable) {
                continue;
            }

            if ($token->getPaymentMethodCode() !== Standard::METHOD_CODE
                || $hostedFieldsIdentifier === null
                || $hostedFieldsIdentifier !== $currentIdentifier
            ) {
                continue;
            }

            $hostedFieldsTokens[] = [
                self::TOKEN_OBJECT_KEY => $token,
                self::TOKEN_DETAILS_KEY => $tokenDetails,
            ];
        }

        return $hostedFieldsTokens;
    }
}
