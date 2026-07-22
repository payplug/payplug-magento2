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
use Payplug\Payments\Model\OrderPaymentRepository;
use Payplug\Resource\APIResource;
use Payplug\Resource\Payment as PayplugPaymentResource;

class StandardClient extends AbstractClient
{
    /**
     * @param PayplugOrderPaymentFactory $payplugOrderPaymentFactory
     * @param OrderPaymentRepository $orderPaymentRepository
     * @param PayplugLogger $payplugLogger
     * @param Config $payplugConfig
     */
    public function __construct(
        private readonly PayplugOrderPaymentFactory $payplugOrderPaymentFactory,
        private readonly OrderPaymentRepository $orderPaymentRepository,
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

        /** Payment object is Payplug Retail */
        if (is_array($result) === false || isset($payplugData['params']['HASH']) === false) {
            return $result;
        }

        /** Payment object is Hosted Fields */
        /** @var array $result */
        $execCode = $result['EXECCODE'] ?? null;
        $orderIncrementId = $payplugData['params']['ORDERID'] ?? null;
        $transactionId = $result['TRANSACTIONID'] ?? null;
        $redirectUrl = $result['REDIRECTURL'] ?? null;
        $redirectPostParams = $result['REDIRECTPOSTPARAMS'] ?? null;

        $payplugOrderPayment = $this->payplugOrderPaymentFactory->create();
        $payplugOrderPayment->setIsHostedFieldsPayment(true);

        if ($orderIncrementId !== null) {
            $payplugOrderPayment->setOrderId($orderIncrementId);
        }
        if ($transactionId !== null) {
            $payplugOrderPayment->setPaymentId($transactionId);
        }

        if ($execCode === '0000' && $orderIncrementId !== null) {
            return $payplugOrderPayment->retrieve();
        }

        if ($execCode === '0001' && $transactionId !== null && $redirectUrl !== null) {
            return PayplugPaymentResource::fromAttributes([
                'id' => $transactionId,
                'is_paid' => false,
                'failure' => null,
                'is_live' => true, // No test mode for Hosted fields. Live by default.
                'hosted_payment' => [
                    'payment_url' => $redirectUrl,
                    'payment_url_post_params' => $redirectPostParams,
                ]
            ]);
        }

        $this->orderPaymentRepository->save($payplugOrderPayment);

        $this->payplugLogger->error(sprintf('Hosted field payment rejected : code %s', $execCode));

        throw new LocalizedException(__(
            'The transaction was aborted and your card has not been charged'
        ));
    }
}
