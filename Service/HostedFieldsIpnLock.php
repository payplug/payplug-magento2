<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Service;

use Magento\Framework\Lock\LockManagerInterface;
use Payplug\Payments\Logger\Logger;
use Throwable;

class HostedFieldsIpnLock
{
    private const LOCK_PREFIX = 'payplug_hf_ipn_';
    public const LOCK_TIMEOUT = 5;

    /**
     * @param LockManagerInterface $lockManager
     * @param Logger $logger
     */
    public function __construct(
        private readonly LockManagerInterface $lockManager,
        private readonly Logger $logger
    ) {
    }

    /**
     * Acquire the lock for the given order, blocking up to $timeout seconds
     *
     * @param string $orderIncrementId
     * @param int $timeout
     * @return bool
     */
    public function acquire(string $orderIncrementId, int $timeout = self::LOCK_TIMEOUT): bool
    {
        if ($orderIncrementId === '') {
            return false;
        }

        try {
            return $this->lockManager->lock(self::LOCK_PREFIX . $orderIncrementId, $timeout);
        } catch (Throwable $e) {
            $this->logger->error('Could not acquire Hosted Fields IPN lock: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Release the lock for the given order.
     *
     * @param string $orderIncrementId
     * @return void
     */
    public function release(string $orderIncrementId): void
    {
        if ($orderIncrementId === '') {
            return;
        }

        try {
            $this->lockManager->unlock(self::LOCK_PREFIX . $orderIncrementId);
        } catch (Throwable $e) {
            $this->logger->error('Could not release Hosted Fields IPN lock: ' . $e->getMessage());
        }
    }

    /**
     * Block until the checkout request has released the lock (order payment row committed), then return.
     *
     * @param string $orderIncrementId
     * @return void
     */
    public function waitForRelease(string $orderIncrementId): void
    {
        if ($this->acquire($orderIncrementId)) {
            $this->release($orderIncrementId);
        }
    }
}
