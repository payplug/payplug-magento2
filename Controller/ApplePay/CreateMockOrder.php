<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\ApplePay;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\QuoteManagement;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Payplug\Payments\Logger\Logger;

class CreateMockOrder implements HttpGetActionInterface
{
    public function __construct(
        private JsonFactory $resultJsonFactory,
        private CheckoutSession $checkoutSession,
        private CartRepositoryInterface $cartRepository,
        private CartManagementInterface $cartManagement,
        private Logger $logger,
        private OrderRepositoryInterface $orderRepository
    ) {
    }

    /**
     * Create a mock order from a cart to pay with ApplePay.
     * Callable from a GET
     */
    public function execute(): Json
    {
        $this->logger->info("Creating mock order");
        /** @var Json $result */
        $result = $this->resultJsonFactory->create();
        $response = [
            'error' => true,
            'message' => __('An error occurred while processing the order.'),
        ];

        try {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote || !$quote->getId()) {
                throw new LocalizedException(__('No active quote found.'));
            }

            $quote->setIsActive(true);
            $quote->setCheckoutMethod(QuoteManagement::METHOD_GUEST);
            if (!$quote->getCustomerEmail()) {
                $quote->setCustomerEmail('placeholder@applepay.com');
            }
            $quote->setCustomerIsGuest(true);

            $placeholderAddress = [
                'givenName' => 'ApplePay',
                'familyName' => 'Customer',
                'locality' => 'Placeholder City',
                'postalCode' => '00000',
                'administrativeArea' => 'Placeholder Region',
                'countryCode' => 'US'
            ];

            $this->updateQuoteBillingAddress($quote, $placeholderAddress);
            $this->updateQuoteShippingAddress($quote, $placeholderAddress);

            $quote->setPaymentMethod('payplug_payments_apple_pay');
            $payment = $quote->getPayment();
            $payment->setMethod('payplug_payments_apple_pay');

            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setShippingMethod('flatrate_flatrate');
            $shippingAddress->setCollectShippingRates(true)->collectShippingRates();

            $quote->collectTotals();
            $this->cartRepository->save($quote);

            $orderId = $this->cartManagement->placeOrder($quote->getId());
            if (!$orderId) {
                throw new LocalizedException(__('Order could not be created.'));
            }

            $order = $this->orderRepository->get($orderId);
            $merchantSession = $order->getPayment()->getAdditionalInformation('merchand_session');
            $order->getPayment()->unsAdditionalInformation('merchand_session');

            if (empty($merchantSession)) {
                throw new \Exception('Could not retrieve merchant session');
            }

            $response['error'] = false;
            $response['message'] = __('Order placed successfully.');
            $response['order_id'] = $orderId;
            $response['merchantSession'] = $merchantSession;
        } catch (\Exception $e) {
            $this->logger->info($e->getMessage());
            $response['message'] = $e->getMessage();
        }

        return $result->setData($response);
    }

    private function updateQuoteBillingAddress(CartInterface $quote, array $appleBilling): void
    {
        $billingAddress = $quote->getBillingAddress();

        $billingAddress->setFirstname($appleBilling['givenName'] ?? 'ApplePay')
            ->setLastname($appleBilling['familyName'] ?? 'Customer')
            ->setCity($appleBilling['locality'] ?? 'Unknown')
            ->setPostcode($appleBilling['postalCode'] ?? '')
            ->setRegion($appleBilling['administrativeArea'] ?? '')
            ->setCountryId($appleBilling['countryCode'] ?? 'US')
            ->setRegion('Alabama')
            ->setRegionId(1)
            ->setTelephone('0000000000')
            ->setShouldIgnoreValidation(true);

        $street = $appleBilling['addressLines'] ?? ['Apple Pay Billing'];
        $billingAddress->setStreet($street);

        if (!empty($appleBilling['emailAddress'])) {
            $billingAddress->setEmail($appleBilling['emailAddress']);
        } else {
            $billingAddress->setEmail('guest@applepay.com');
        }
    }

    private function updateQuoteShippingAddress(CartInterface $quote, array $appleShipping): void
    {
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setFirstname($appleShipping['givenName'] ?? 'ApplePay')
            ->setLastname($appleShipping['familyName'] ?? 'Customer')
            ->setCity($appleShipping['locality'] ?? 'Unknown')
            ->setPostcode($appleShipping['postalCode'] ?? '')
            ->setRegion($appleShipping['administrativeArea'] ?? '')
            ->setCountryId($appleShipping['countryCode'] ?? 'US')
            ->setRegion('Alabama')
            ->setRegionId(1)
            ->setTelephone('0000000000')
            ->setShouldIgnoreValidation(true);

        $street = $appleShipping['addressLines'] ?? ['Apple Pay Shipping'];
        $shippingAddress->setStreet($street);
    }
}
