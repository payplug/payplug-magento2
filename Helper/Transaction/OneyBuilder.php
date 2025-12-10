<?php

declare(strict_types=1);

namespace Payplug\Payments\Helper\Transaction;

use DateMalformedStringException;
use Exception;
use Laminas\Uri\Http as UriHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Helper\Country;
use Payplug\Payments\Helper\Oney;
use Payplug\Payments\Helper\Phone;
use Payplug\Payments\Logger\Logger;

class OneyBuilder extends AbstractBuilder
{
    /**
     * @param Oney $oneyHelper
     * @param Context $context
     * @param Config $payplugConfig
     * @param Country $countryHelper
     * @param Phone $phoneHelper
     * @param Logger $logger
     * @param FormKey $formKey
     * @param UriHelper $uriHelper
     */
    public function __construct(
        private readonly Oney $oneyHelper,
        Context $context,
        Config $payplugConfig,
        Country $countryHelper,
        Phone $phoneHelper,
        Logger $logger,
        FormKey $formKey,
        UriHelper $uriHelper
    ) {
        parent::__construct($context, $payplugConfig, $countryHelper, $phoneHelper, $logger, $formKey, $uriHelper);
    }

    /**
     * @inheritdoc
     *
     * @throws LocalizedException
     */
    public function buildTransaction($order, InfoInterface $payment, $quote): array
    {
        $this->validateTransaction($order, $payment);

        return parent::buildTransaction($order, $payment, $quote);
    }

    /**
     * Validate Oney payment before creating PayPlug transaction
     *
     * @param OrderInterface|OrderAdapterInterface $order
     * @param InfoInterface $payment
     * @return void
     * @throws LocalizedException
     */
    private function validateTransaction($order, InfoInterface $payment): void
    {
        try {
            $this->oneyHelper->oneyCheckoutValidation(
                $order->getBillingAddress()->getCountryId(),
                $order->getShippingAddress() !== null ? $order->getShippingAddress()->getCountryId() : null,
                $this->getOrderItemsCount($order->getItems())
            );
            $this->oneyHelper->oneyValidation(
                $order->getGrandTotalAmount(),
                $order->getBillingAddress()->getCountryId(),
                $order->getStoreId(),
                $order->getCurrencyCode()
            );

            $oneyOption = $payment->getAdditionalInformation('payplug_payments_oney_option');
            $this->oneyHelper->validateOneyOption($payment->getMethodInstance()->getCode(), $oneyOption);
            $this->validateBillingMobilePhone($order);
        } catch (Exception $e) {
            $this->logger->error(sprintf(
                "A customer tried to pay with Oney but an error occurred : %s",
                $e->getMessage()
            ));
            throw new LocalizedException(__($e->getMessage()));
        }
    }

    /**
     * Get order items count
     *
     * @param array|OrderItemInterface[] $items
     *
     * @return int
     */
    private function getOrderItemsCount(?array $items): int
    {
        $count = 0;
        foreach ($items as $item) {
            if ($item->isDeleted() || $item->getHasChildren()) {
                continue;
            }

            $count += (int) $item->getQtyOrdered();
        }

        return $count;
    }

    /**
     * Validate billing phone for Oney payment
     *
     * @param OrderAdapterInterface|OrderInterface $order
     *
     * @throws Exception
     */
    private function validateBillingMobilePhone($order): void
    {
        $exceptionMessage = (string)__('Please fill in a mobile phone on your billing address.');
        $address = $order->getBillingAddress();

        if (empty($address->getTelephone())) {
            throw new Exception($exceptionMessage);
        }

        $phoneResult = $this->phoneHelper->getPhoneInfo($address->getTelephone(), $address->getCountryId());

        if (!is_array($phoneResult)) {
            throw new Exception($exceptionMessage);
        }

        if (!$phoneResult['mobile']) {
            throw new Exception($exceptionMessage);
        }
    }

    /**
     * Get shipping method
     *
     * @param CartInterface $quote
     *
     * @return string|null
     */
    private function getShippingMethod(CartInterface $quote): ?string
    {
        return $quote->isVirtual() ? null : $quote->getShippingAddress()->getShippingMethod();
    }

    /**
     * Build cart data
     *
     * @param OrderInterface|OrderAdapterInterface $order
     * @param CartInterface $quote
     * @return array
     *
     * @throws LocalizedException|DateMalformedStringException
     */
    private function buildCartContext($order, CartInterface $quote): array
    {
        $shippingMethod = $this->getShippingMethod($quote);
        $shippingMapping = $this->oneyHelper->getShippingMethodMapping($shippingMethod);

        $brand = sprintf(
            '%s - %s - %s',
            $quote->getStore()->getWebsite()->getName(),
            $quote->getStore()->getGroup()->getName(),
            $quote->getStore()->getName()
        );
        $deliveryLabel = $brand;
        if ($shippingMethod !== null) {
            $deliveryLabel = $quote->getShippingAddress()->getShippingDescription();
        }
        $deliveryDate = new \DateTime();
        if ($shippingMapping['period'] > 0) {
            $deliveryDate->modify(sprintf('+ %d days', $shippingMapping['period']));
        }
        $deliveryDate = $deliveryDate->format('Y-m-d');
        $deliveryType = $shippingMapping['type'];

        $products = [];
        /** @var OrderItemInterface $item */
        foreach ($order->getItems() as $item) {
            if ($item->isDeleted() || $item->getHasChildren()) {
                continue;
            }

            $parentItem = null;
            if ($item->getProductType() === 'simple' &&
                $item->getParentItem() &&
                $item->getParentItem()->getProductType() === 'configurable'
            ) {
                $parentItem = $item->getParentItem();
            }

            if (!isset($products[$item->getSku()])) {
                $unitPrice = $item->getPriceInclTax();
                if ($parentItem !== null) {
                    $unitPrice = $parentItem->getPriceInclTax();
                }
                $products[$item->getSku()] = [
                    'delivery_label' => $deliveryLabel,
                    'delivery_type' => $deliveryType,
                    'brand' => $brand,
                    'merchant_item_id' => $item->getSku(),
                    'name' => $item->getName(),
                    'expected_delivery_date' => $deliveryDate,
                    'total_amount' => 0,
                    'price' => (int) round($unitPrice * 100),
                    'quantity' => 0
                ];
            }
            $price = $item->getRowTotalInclTax();
            if ($parentItem !== null) {
                $price = $parentItem->getRowTotalInclTax();
            }
            $products[$item->getSku()]['total_amount'] += (int) round($price * 100);
            $products[$item->getSku()]['quantity'] += (int) $item->getQtyOrdered();
        }
        $products = array_values($products);

        return ['payment_context' => ['cart' => $products]];
    }

    /**
     * @inheritdoc
     *
     * @throws LocalizedException
     */
    public function buildCustomerData($order, InfoInterface $payment, $quote): array
    {
        $customerData = parent::buildCustomerData($order, $payment, $quote);

        $companyName = null;
        if ($order->getShippingAddress() !== null) {
            $companyName = $order->getShippingAddress()->getCompany();
            if (empty($companyName)) {
                $companyName = $order->getShippingAddress()->getFirstname() . ' ' .
                    $order->getShippingAddress()->getLastname();
            }
        } else {
            $companyName = $order->getBillingAddress()->getCompany();
            if (empty($companyName)) {
                $companyName = $order->getBillingAddress()->getFirstname() . ' ' .
                    $order->getBillingAddress()->getLastname();
            }
        }
        if (empty($companyName)) {
            throw new LocalizedException(__('Please fill in your shipping company information.'));
        }

        $customerData['shipping']['company_name'] = $companyName;

        $email = $customerData['billing']['email'];
        $replaceResult = preg_replace('/^([^\+]+)(\+.*)(@.*)$/', '$1$3', $email);
        if ($replaceResult !== null) {
            $customerData['billing']['email'] = $replaceResult;
            $customerData['shipping']['email'] = $replaceResult;
        }

        return $customerData;
    }

    /**
     * @inheritdoc
     */
    public function buildAmountData($order): array
    {
        $amountData = parent::buildAmountData($order);
        $amountData['authorized_amount'] = $amountData['amount'];
        unset($amountData['amount']);

        return $amountData;
    }

    /**
     * @inheritdoc
     */
    public function buildPaymentData($order, InfoInterface $payment, CartInterface $quote): array
    {
        $paymentData = parent::buildPaymentData($order, $payment, $quote);

        $paymentData['auto_capture'] = true;
        $oneyOption = $this->oneyHelper->validateOneyOption(
            $payment->getMethodInstance()->getCode(),
            $payment->getAdditionalInformation('payplug_payments_oney_option')
        );
        $paymentData['payment_method'] = 'oney_' . $oneyOption;

        return array_merge($paymentData, $this->buildCartContext($order, $quote));
    }
}
