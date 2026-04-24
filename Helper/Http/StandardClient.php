<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Helper\Http;

use Magento\Framework\Exception\LocalizedException;
use Payplug\Exception\ConfigurationException;
use Payplug\Exception\ConfigurationNotSetException;
use Payplug\Payment;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Logger\Logger as PayplugLogger;
use Payplug\Payments\Model\Order\PaymentFactory as PayplugOrderPaymentFactory;
use Payplug\Resource\APIResource;
use Payplug\Resource\Payment as PayplugPaymentResource;

class StandardClient extends AbstractClient
{
    /**
     * @param PayplugOrderPaymentFactory $payplugOrderPaymentFactory
     * @param PayplugLogger $payplugLogger
     * @param Config $payplugConfig
     */
    public function __construct(
        private readonly PayplugOrderPaymentFactory $payplugOrderPaymentFactory,
        private readonly PayplugLogger $payplugLogger,
        Config $payplugConfig
    ) {
        parent::__construct($payplugConfig);
    }

    /**
     * @inheritdoc
     */
    protected function prepareReturnData(APIResource $payplugObject, array $data): array
    {
        return ['payment' => $payplugObject];
    }

    /**
     * @inheritdoc
     *
     * @throws ConfigurationNotSetException
     * @throws LocalizedException
     * @throws ConfigurationException
     */
    protected function createPayplugObject(array $payplugData): ?APIResource
    {
        $result = Payment::create($payplugData);

        if (is_array($result) && isset($payplugData['params']['HFTOKEN'])) {
            $execCode = $result['EXECCODE'] ?? null;
            $orderIncrementId = $payplugData['params']['ORDERID'] ?? null;
            $transactionId = $result['TRANSACTIONID'] ?? null;
            $redirectUrl = $result['REDIRECTURL'] ?? null;
            $redirectPostParams = $result['REDIRECTPOSTPARAMS'] ?? null;

            if ($execCode === '0000' && $orderIncrementId !== null) {
                $payplugOrderPayment = $this->payplugOrderPaymentFactory->create();
                $payplugOrderPayment->setOrderId($orderIncrementId);
                $payplugOrderPayment->setIsHostedFieldsPayment(true);

                return $payplugOrderPayment->retrieve();
            }

            if ($execCode === '0001' && $transactionId !== null && $redirectUrl !== null) {
                return PayplugPaymentResource::fromAttributes([
                    'id' => $transactionId,
                    'is_paid' => false,
                    'failure' => null,
                    'is_live' => true,
                    'hosted_payment' => [
                        'payment_url' => $redirectUrl,
                        'payment_url_post_params' => $redirectPostParams,
                    ]
                ]);
            }

            $this->payplugLogger->error(sprintf('Operation rejected : code %s', $execCode));

            if (str_starts_with($execCode, '4') || str_starts_with($execCode, '6')) {
                throw new LocalizedException(__(
                    'The transaction was aborted and your card has not been charged.'
                ));
            }

            throw new LocalizedException(__(
                'An error occurred on the server. Please try to place the order again.'
            ));
        }

        return $result;
    }
}
