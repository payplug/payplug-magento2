<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Service;

use DateMalformedStringException;
use DateTime;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderItemInterface;

class GetCartContextForTransaction
{
    /**
     * Get cart context data
     *
     * @param OrderAdapterInterface $order
     * @param CartInterface $quote
     * @return array
     */
    public function execute(OrderAdapterInterface $order, CartInterface $quote): array
    {
        try {
            $websiteName = $quote->getStore()->getWebsite()->getName();
            $groupName = $quote->getStore()->getGroup()->getName();
        } catch (NoSuchEntityException) {
            return [];
        }

        $shippingMethod = $quote->isVirtual() ? null : $quote->getShippingAddress()->getShippingMethod();
        $shippingMapping = [
            'type' => $shippingMethod === null ? 'edelivery' : 'storepickup',
            'period' => $shippingMethod === null ? 3 : 0,
        ];

        $deliveryLabel = $brand = sprintf('%s - %s - %s', $websiteName, $groupName, $quote->getStore()->getName());

        if ($shippingMethod !== null) {
            $deliveryLabel = $quote->getShippingAddress()->getShippingDescription();
        }

        $deliveryDate = new DateTime();

        if ($shippingMapping['period'] > 0) {
            try {
                $deliveryDate->modify(sprintf('+ %d days', $shippingMapping['period']));
            } catch (DateMalformedStringException) {
                return [];
            }
        }

        $deliveryDate = $deliveryDate->format('Y-m-d');
        $deliveryType = $shippingMapping['type'];

        $products = [];

        /** @var OrderItemInterface $item */
        foreach ($order->getItems() as $item) {
            if ($item->isDeleted() || $item->getData('has_children')) {
                continue;
            }

            $parentItem = null;

            if ($item->getProductType() === ProductType::TYPE_SIMPLE && $item->getParentItem()
                && $item->getParentItem()->getProductType() === ConfigurableType::TYPE_CODE
            ) {
                $parentItem = $item->getParentItem();
            }

            $productSku = $item->getSku();

            if (!isset($products[$productSku])) {
                $unitPrice = $item->getPriceInclTax();

                if ($parentItem !== null) {
                    $unitPrice = $parentItem->getPriceInclTax();
                }

                $products[$productSku] = [
                    'delivery_label' => $deliveryLabel,
                    'delivery_type' => $deliveryType,
                    'brand' => $brand,
                    'merchant_item_id' => $productSku,
                    'name' => $item->getName(),
                    'expected_delivery_date' => $deliveryDate,
                    'total_amount' => 0,
                    'price' => (int) round($unitPrice * 100),
                    'quantity' => 0
                ];
            }

            $price = $parentItem !== null ? $parentItem->getRowTotalInclTax() : $item->getRowTotalInclTax();

            $products[$productSku]['total_amount'] += (int) round($price * 100);
            $products[$productSku]['quantity'] += (int) $item->getQtyOrdered();
        }

        return ['payment_context' => ['cart' => array_values($products)]];
    }
}
