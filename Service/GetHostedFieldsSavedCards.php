<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\PaymentTokenManagement;
use Payplug\Payments\Gateway\Config\Standard;
use Throwable;

class GetHostedFieldsSavedCards
{
    /**
     * @param PaymentTokenManagement $paymentTokenManagement
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly PaymentTokenManagement $paymentTokenManagement,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Get Hosted Fields saved cards
     *
     * @param int $customerId
     * @return PaymentTokenInterface[]
     */
    public function execute(int $customerId): array
    {
        $hostedFieldsTokens = [];

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

            if ($token->getPaymentMethodCode() !== Standard::METHOD_CODE || $hostedFieldsIdentifier === null) {
                continue;
            }

            $hostedFieldsTokens[] = $token;
        }

        return $hostedFieldsTokens;
    }
}
