<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Model\Order;

use Magento\Framework\App\ScopeInterface as ScopeInterfaceDefault;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Exception\ConfigurationException;
use Payplug\Exception\ConfigurationNotSetException;
use Payplug\Payments\Helper\Config;
use Payplug\Resource\Payment as ResourcePayment;
use Payplug\Resource\Refund;

class Payment extends AbstractModel implements IdentityInterface
{
    public const CACHE_TAG = 'payplug_payments_order_payment';

    public const ORDER_ID = 'order_id';

    public const PAYMENT_ID = 'payment_id';

    public const IS_SANDBOX = 'is_sandbox';

    public const IS_INSTALLMENT_PLAN_PAYMENT_PROCESSED = 'is_installment_plan_payment_processed';

    public const SENT_BY = 'sent_by';

    public const SENT_BY_VALUE = 'sent_by_value';

    public const LANGUAGE = 'language';

    public const DESCRIPTION = 'description';

    public const SENT_BY_SMS = 'SMS';
    public const SENT_BY_EMAIL = 'EMAIL';

    /**
     * @param Context $context
     * @param Registry $registry
     * @param Config $payplugConfig
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     * @throws LocalizedException
     */
    public function __construct(
        Context $context,
        Registry $registry,
        private Config $payplugConfig,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(\Payplug\Payments\Model\ResourceModel\Order\Payment::class);
    }

    /**
     * If the payment is still being processed
     *
     * @param ResourcePayment $resourcePayment
     * @return bool
     */
    public function isProcessing(ResourcePayment $resourcePayment): bool
    {
        $isProcessing = (!$resourcePayment->is_paid && empty($resourcePayment->failure));

        if ($isProcessing && isset($resourcePayment->id)) {
            $this->_logger->info(sprintf('Payment payment_id %s is still processing.', $resourcePayment->id));
        }

        return $isProcessing;
    }

    /**
     * Get entity identities
     */
    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * Get order increment_id
     */
    public function getOrderId(): string
    {
        return (string)$this->_getData(self::ORDER_ID);
    }

    /**
     * Set order id
     *
     * @param string $orderId
     * @return self
     */
    public function setOrderId(string $orderId): self
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * Get payment id
     */
    public function getPaymentId(): string
    {
        return $this->_getData(self::PAYMENT_ID);
    }

    /**
     * Set payment id
     *
     * @param string $paymentId
     * @return self
     */
    public function setPaymentId(string $paymentId): self
    {
        return $this->setData(self::PAYMENT_ID, $paymentId);
    }

    /**
     * Get is sandbox
     */
    public function isSandbox(): bool
    {
        return (bool)$this->_getData(self::IS_SANDBOX);
    }

    /**
     * Set is sandbox
     *
     * @param bool $isSandbox
     * @return self
     */
    public function setIsSandbox(bool $isSandbox): self
    {
        return $this->setData(self::IS_SANDBOX, $isSandbox);
    }

    /**
     * Get installment plan processed flag
     */
    public function isInstallmentPlanPaymentProcessed(): bool
    {
        return (bool)$this->_getData(self::IS_INSTALLMENT_PLAN_PAYMENT_PROCESSED);
    }

    /**
     * Set installment plan processed flag
     *
     * @param bool $isProcessed
     * @return self
     */
    public function setIsInstallmentPlanPaymentProcessed(bool $isProcessed): self
    {
        return $this->setData(self::IS_INSTALLMENT_PLAN_PAYMENT_PROCESSED, $isProcessed);
    }

    /**
     * Get sent by
     */
    public function getSentBy(): ?string
    {
        return $this->_getData(self::SENT_BY);
    }

    /**
     * Set sent by
     *
     * @param string|null $sentBy
     * @return self
     */
    public function setSentBy(?string $sentBy = null): self
    {
        return $this->setData(self::SENT_BY, $sentBy);
    }

    /**
     * Get sent by value
     */
    public function getSentByValue(): ?string
    {
        return $this->_getData(self::SENT_BY_VALUE);
    }

    /**
     * Set sent by value
     *
     * @param string|null $sentByValue
     * @return self
     */
    public function setSentByValue(?string $sentByValue = null): self
    {
        return $this->setData(self::SENT_BY_VALUE, $sentByValue);
    }

    /**
     * Get language
     */
    public function getLanguage(): ?string
    {
        return $this->_getData(self::LANGUAGE);
    }

    /**
     * Set language
     *
     * @param string|null $language
     * @return self
     */
    public function setLanguage(?string $language = null): self
    {
        return $this->setData(self::LANGUAGE, $language);
    }

    /**
     * Get description
     */
    public function getDescription(): ?string
    {
        return $this->_getData(self::DESCRIPTION);
    }

    /**
     * Set description
     *
     * @param string|null $description
     * @return self
     */
    public function setDescription(?string $description = null): self
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    /**
     * Get scope
     *
     * @param OrderInterface $order
     * @return string
     */
    public function getScope(OrderInterface $order): string
    {
        if ($order->getStoreId()) {
            return ScopeInterface::SCOPE_STORES;
        } elseif ($order->getStore()->getWebsiteId()) {
            return ScopeInterface::SCOPE_WEBSITES;
        }

        return ScopeInterfaceDefault::SCOPE_DEFAULT;
    }

    /**
     * Get scope ID
     *
     * @param OrderInterface $order
     * @return int
     */
    public function getScopeId(OrderInterface $order): int
    {
        if ($order->getStoreId()) {
            // Use store ID if non-zero
            return (int)$order->getStoreId();
        } elseif ($order->getStore()->getWebsiteId()) {
            // Otherwise use website ID if store ID == 0
            return (int)$order->getStore()->getWebsiteId();
        }
        // Otherwise default scope ID = 0
        return 0;
    }

    /**
     * Retrieve a payment
     *
     * @param int|null $store
     * @param string|null $scope
     * @return ResourcePayment
     * @throws ConfigurationException
     * @throws ConfigurationNotSetException
     */
    public function retrieve(?int $store = null, ?string $scope = ScopeInterface::SCOPE_STORE): ResourcePayment
    {
        $this->payplugConfig->setPayplugApiKey($store, $this->isSandbox(), $scope);

        return \Payplug\Payment::retrieve($this->getPaymentId());
    }

    /**
     * Attempt to refund partially or totally a payment
     *
     * @param float $amount
     * @param array|null $metadata
     * @param int|null $store
     * @return Refund
     * @throws ConfigurationException
     * @throws ConfigurationNotSetException
     */
    public function makeRefund(float $amount, ?array $metadata, ?int $store = null): Refund
    {
        $payplugPayment = $this->retrieve($store);

        if (!empty($payplugPayment->metadata)) {
            $metadata = (array) $metadata;
            $metadata += $payplugPayment->metadata;
        }

        if (!empty($payplugPayment->id)) {
            $metadata = (array) $metadata;
            $metadata['Payment ID'] = $payplugPayment->id;
        }

        $data = [
            'amount' => $amount * 100,
            'metadata' => $metadata
        ];

        $this->payplugConfig->setPayplugApiKey($store, $this->isSandbox());

        return \Payplug\Refund::create($this->getPaymentId(), $data);
    }

    /**
     * Abort an installment plan
     *
     * @param int|null $store
     * @return ResourcePayment
     * @throws ConfigurationException
     * @throws ConfigurationNotSetException
     */
    public function abort(?int $store = null): ResourcePayment
    {
        $this->payplugConfig->setPayplugApiKey($store, $this->isSandbox());

        return \Payplug\Payment::abort($this->getPaymentId());
    }

    /**
     * Update a payment
     *
     * @param array $data
     * @param int|null $store
     * @return ResourcePayment
     * @throws ConfigurationException
     * @throws ConfigurationNotSetException
     */
    public function update(array $data, ?int $store = null): ResourcePayment
    {
        $this->payplugConfig->setPayplugApiKey($store, $this->isSandbox());

        $payment = ResourcePayment::fromAttributes(['id' => $this->getPaymentId()]);

        return $payment->update($data);
    }
}
