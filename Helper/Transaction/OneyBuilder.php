<?php

namespace Payplug\Payments\Helper\Transaction;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order\Item;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Helper\Country;
use Payplug\Payments\Helper\Oney;
use Payplug\Payments\Helper\Phone;
use Payplug\Payments\Logger\Logger;

class OneyBuilder extends AbstractBuilder
{
    /**
     * @var Oney
     */
    private $oneyHelper;

    /**
     * @param Context $context
     * @param Config  $payplugConfig
     * @param Country $countryHelper
     * @param Phone   $phoneHelper
     * @param Logger  $logger
     * @param Oney    $oneyHelper
     */
    public function __construct(
        Context $context,
        Config $payplugConfig,
        Country $countryHelper,
        Phone $phoneHelper,
        Logger $logger,
        Oney $oneyHelper
    ) {
        parent::__construct($context, $payplugConfig, $countryHelper, $phoneHelper, $logger);

        $this->oneyHelper = $oneyHelper;
    }

    /**
     * @inheritdoc
     */
    public function buildTransaction($order, $payment, $quote)
    {
        $this->validateTransaction($order, $payment, $quote);

        return parent::buildTransaction($order, $payment, $quote);
    }

    /**
     * @param OrderAdapterInterface $order
     * @param InfoInterface         $payment
     * @param Quote                 $quote
     *
     * @throws LocalizedException
     */
    private function validateTransaction($order, $payment, $quote)
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
            $this->oneyHelper->validateOneyOption($oneyOption);
            $this->validateBillingMobilePhone($order);
        } catch (\Exception $e) {
            $this->logger->error(sprintf("A customer tried to pay with Oney but an error occurred : %s", $e->getMessage()));
            throw new LocalizedException(__($e->getMessage()));
        }
    }

    /**
     * @param array|Item[] $items
     *
     * @return int
     */
    private function getOrderItemsCount($items)
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
     * @param OrderAdapterInterface $order
     *
     * @throws \Exception
     */
    private function validateBillingMobilePhone($order)
    {
        $exceptionMessage = __('Please fill in a mobile phone on your billing address.');
        $address = $order->getBillingAddress();
        if (empty($address->getTelephone())) {
            throw new \Exception($exceptionMessage);
        }
        $phoneResult = $this->phoneHelper->getPhoneInfo($address->getTelephone(), $address->getCountryId());
        $mobile = null;
        $landline = null;
        if (!is_array($phoneResult)) {
            throw new \Exception($exceptionMessage);
        }
        if (!$phoneResult['mobile']) {
            throw new \Exception($exceptionMessage);
        }
    }

    /**
     * @param Quote $quote
     *
     * @return string|null
     */
    private function getShippingMethod($quote)
    {
        return $quote->isVirtual() ? null : $quote->getShippingAddress()->getShippingMethod();
    }

    /**
     * @param OrderAdapterInterface $order
     * @param Quote                 $quote
     *
     * @return array
     *
     * @throws LocalizedException
     */
    private function buildCartContext($order, $quote)
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
        /** @var \Magento\Sales\Api\Data\OrderItemInterface|Item $item */
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
     */
    public function buildCustomerData($order, $payment, $quote)
    {
        $customerData = parent::buildCustomerData($order, $payment, $quote);

        $companyName = null;
        if ($order->getShippingAddress() !== null) {
            $companyName = $order->getShippingAddress()->getCompany();
            if (empty($companyName)) {
                $companyName = $order->getShippingAddress()->getFirstname() . ' ' . $order->getShippingAddress()->getLastname();
            }
        } else {
            $companyName = $order->getBillingAddress()->getCompany();
            if (empty($companyName)) {
                $companyName = $order->getBillingAddress()->getFirstname() . ' ' . $order->getBillingAddress()->getLastname();
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
    public function buildAmountData($order)
    {
        $amountData = parent::buildAmountData($order);
        $amountData['authorized_amount'] = $amountData['amount'];
        unset($amountData['amount']);

        return $amountData;
    }

    /**
     * @inheritdoc
     */
    public function buildPaymentData($order, $payment, $quote)
    {
        $paymentData = parent::buildPaymentData($order, $payment, $quote);

        $paymentData['auto_capture'] = true;
        $oneyOption = $this->oneyHelper->validateOneyOption($payment->getAdditionalInformation('payplug_payments_oney_option'));
        $paymentData['payment_method'] = 'oney_' . $oneyOption;

        $paymentData = array_merge($paymentData, $this->buildCartContext($order, $quote));

        return $paymentData;
    }
}
