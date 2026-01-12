<?php

declare(strict_types=1);

namespace Payplug\Payments\Plugin;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Payplug\Payments\Helper\Config as PayplugConfigHelper;
use Payplug\Payments\Helper\Data as PayplugDataHelper;
use Payplug\Payments\Logger\Logger as PayplugLogger;
use Payplug\Payments\Model\Config\Source\CustomMetadata as CustomMetadataSource;
use Throwable;

class SendCustomMetadataAfterPlaceOrder
{
    /**
     * @param PayplugConfigHelper $payplugConfigHelper
     * @param PayplugDataHelper $payplugDataHelper
     * @param PayplugLogger $payplugLogger
     * @param OrderRepositoryInterface $orderRepository
     * @param CustomMetadataSource $customMetadataSource
     */
    public function __construct(
        private readonly PayplugConfigHelper $payplugConfigHelper,
        private readonly PayplugDataHelper $payplugDataHelper,
        private readonly PayplugLogger $payplugLogger,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CustomMetadataSource $customMetadataSource
    ) {
    }

    /**
     * Send custom metadata after place order
     *
     * @param CartManagementInterface $cartManagement
     * @param int $orderId
     * @return int
     */
    public function afterPlaceOrder(CartManagementInterface $cartManagement, int $orderId): int
    {
        $order = $this->orderRepository->get($orderId);
        $method = $order->getPayment()?->getMethod();

        if ($this->payplugDataHelper->isCodePayplugPayment($method) === false) {
            return $orderId;
        }

        $currentCustomMetadataKey = $this->payplugConfigHelper->getConfigValue('custom_metadata');

        if (empty($currentCustomMetadataKey)) {
            return $orderId;
        }

        try {
            $payplugPayment = $this->payplugDataHelper->getOrderPayment($order->getIncrementId());
        } catch (NoSuchEntityException) {
            $this->payplugLogger->error('Could not retrieve order payment when sending custom metadata');
            return $orderId;
        }

        try {
            $resourcePayment = $payplugPayment->retrieve((int)$order->getStoreId());

            /** @var array $currentMetadata */
            $currentMetadata = $resourcePayment->__get('metadata');
            $customMetadataOptions = $this->customMetadataSource->toOptionArray();
            $customMetadataLabel = $currentCustomMetadataKey;

            foreach ($customMetadataOptions as $customMetadataOption) {
                if ($customMetadataOption['value'] === $currentCustomMetadataKey) {
                    $customMetadataLabel = (string)$customMetadataOption['label'];
                    break;
                }
            }

            $customMetadataValue = $order->getData($currentCustomMetadataKey);

            if ($customMetadataValue !== null && $customMetadataValue !== '') {
                $currentMetadata[$customMetadataLabel] = $customMetadataValue;
            }

            $payplugPayment->update(['metadata' => $currentMetadata], (int)$order->getStoreId());
        } catch (Throwable $e) {
            $this->payplugLogger->error($e->getMessage());
        }

        return $orderId;
    }
}
