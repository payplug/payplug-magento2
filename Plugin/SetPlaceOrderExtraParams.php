<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Plugin;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\QuoteGraphQl\Model\Resolver\PlaceOrder as PlaceOrderResolver;
use Payplug\Payments\Service\PlaceOrderExtraParamsRegistry;

class SetPlaceOrderExtraParams
{
    /**
     * @param PlaceOrderExtraParamsRegistry $placeOrderExtraParamsRegistry
     */
    public function __construct(
        private readonly PlaceOrderExtraParamsRegistry $placeOrderExtraParamsRegistry
    ) {
    }

    /**
     * Set custom payment Urls for Payplug
     *
     * @param PlaceOrderResolver $subject
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array|null
     */
    public function beforeResolve(
        PlaceOrderResolver $subject,
        Field $field,
        ContextInterface $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): ?array {

        $input = $args['input'] ?? [];

        $this->placeOrderExtraParamsRegistry->setCustomAfterSuccessUrl($input['payplug_after_success_url'] ?? null);
        $this->placeOrderExtraParamsRegistry->setCustomAfterFailureUrl($input['payplug_after_failure_url'] ?? null);
        $this->placeOrderExtraParamsRegistry->setCustomAfterCancelUrl($input['payplug_after_cancel_url'] ?? null);

        if (!empty($input['cart_id'])) {
            $this->placeOrderExtraParamsRegistry->setMaskedQuoteId($input['cart_id']);
        }

        return null;
    }
}
