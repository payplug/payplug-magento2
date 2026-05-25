<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Gateway\Command;

use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\GatewayCommand as BaseGatewayCommand;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ConverterException;
use Payplug\Payments\Api\Data\OrderPaymentInterface;

class GatewayCommand extends BaseGatewayCommand
{
    /**
     * Execute command
     *
     * @param array $commandSubject
     * @return void
     * @throws CommandException
     * @throws ClientException
     * @throws ConverterException
     */
    public function execute(array $commandSubject): void
    {
        /** @var PaymentDataObject $payment */
        $payment = $commandSubject['payment'];
        $forceHostedFieldsPayment = (bool) ($commandSubject['force_hosted_fields_payment'] ?? false);
        $isHostedFieldsPayment = (bool) $payment->getPayment()->getAdditionalInformation(
            OrderPaymentInterface::HF_PAYMENT_KEY
        );

        if ($isHostedFieldsPayment === true && $forceHostedFieldsPayment === false) {
            return;
        }

        parent::execute($commandSubject);
    }
}
