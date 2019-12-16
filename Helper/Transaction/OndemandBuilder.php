<?php

namespace Payplug\Payments\Helper\Transaction;

use Magento\Framework\Exception\PaymentException;
use Payplug\Payments\Helper\Ondemand;
use Payplug\Payments\Model\Order\Payment;

class OndemandBuilder extends AbstractBuilder
{
    /**
     * @inheritdoc
     */
    public function buildPaymentData($order, $payment, $quote)
    {
        $paymentData = parent::buildPaymentData($order, $payment, $quote);
        unset($paymentData['hosted_payment']['return_url']);
        unset($paymentData['hosted_payment']['cancel_url']);

        $sentBy = trim($payment->getAdditionalInformation('sent_by'));
        $sentByValue = trim($payment->getAdditionalInformation('sent_by_value'));
        $language = trim($payment->getAdditionalInformation('language'));
        $description = trim($payment->getAdditionalInformation('description'));

        $availableSentBy = Payment::getAvailableOndemandSentBy();
        if (!isset($availableSentBy[$sentBy])) {
            throw new PaymentException(__('Invalid sent by option: %1', $sentBy));
        }

        if (empty($sentByValue)) {
            throw new PaymentException(__('Please fill in mobile / email to which the payment link must be sent to'));
        }

        $availableLanguages = Payment::getAvailableOndemandLanguage();
        if (!isset($availableLanguages[$language])) {
            throw new PaymentException(__('Allowed languages are: %1', implode(', ', $availableLanguages)));
        }

        if ($sentBy === Payment::SENT_BY_SMS) {
            $address = $order->getBillingAddress();
            $phoneResult = $this->phoneHelper->getPhoneInfo($sentByValue, $address->getCountryId());
            if (!is_array($phoneResult) || !$phoneResult['mobile']) {
                throw new PaymentException(__('Invalid mobile number %1 for country %2', $sentByValue, $address->getCountryId()));
            }
            $sentByValue = $phoneResult['phone'];
        } elseif ($sentBy === Payment::SENT_BY_EMAIL) {
            if (!\Zend_Validate::is($sentByValue, 'EmailAddress')) {
                throw new PaymentException(__('Invalid email format %1', $sentByValue));
            }
        }

        $description = substr($description, 0, Ondemand::DESCRIPTION_MAX_LENGTH);
        $paymentData['extra'] = [
            'sent_by' => $sentBy,
            'sent_by_value' => $sentByValue,
            'language' => $language,
            'description' => $description,
        ];
        $paymentData['hosted_payment']['sent_by'] = $sentBy;
        $paymentData['description'] = $description;

        return $paymentData;
    }

    /**
     * @inheritdoc
     */
    public function buildTransaction($order, $payment, $quote)
    {
        $transaction = parent::buildTransaction($order, $payment, $quote);

        $sentByKey = 'email';
        if ($transaction['extra']['sent_by'] === Payment::SENT_BY_SMS) {
            $sentByKey = 'mobile_phone_number';
        }
        $transaction['billing'][$sentByKey] = $transaction['extra']['sent_by_value'];
        $transaction['shipping'][$sentByKey] = $transaction['extra']['sent_by_value'];
        $transaction['billing']['language'] = $transaction['extra']['language'];
        $transaction['shipping']['language'] = $transaction['extra']['language'];

        $this->logger->info($transaction);

        return $transaction;
    }
}
