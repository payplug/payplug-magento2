<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\ApplePay\Cart;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Payplug\Payments\Helper\Data as PayplugHelper;

class PlaceOrder extends Action
{
    public function __construct(
        Context $context,
        private JsonFactory $resultJsonFactory,
        private CheckoutSession $checkoutSession,
        private CartRepositoryInterface $cartRepository,
        private CartManagementInterface $cartManagement,
        private PayplugHelper $payplugHelper
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        /** @var Json $result */
        $result = $this->resultJsonFactory->create();
        $response = ['error' => true, 'message' => ''];

        try {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote || !$quote->getId()) {
                throw new LocalizedException(__('No active quote found.'));
            }

            // 1. Grab Apple Pay data from request
            $tokenJson = $this->getRequest()->getParam('token');
            $billingContact = $this->getRequest()->getParam('billingContact');
            $shippingContact = $this->getRequest()->getParam('shippingContact');

            if (!$tokenJson) {
                throw new LocalizedException(__('No Apple Pay token present.'));
            }

            // 2. Update billing address from Apple Pay data
            if (is_array($billingContact)) {
                $this->updateQuoteBillingAddress($quote, $billingContact);
            }

            // 3. Set Payment method and store Apple Pay token or relevant data
            $quote->setPaymentMethod('payplug_payments_apple_pay');
            $payment = $quote->getPayment();
            $payment->setMethod('payplug_payments_apple_pay');
            $payment->setAdditionalInformation('apple_pay_token', $tokenJson);

            // 4. Re-collect totals, save
            $quote->collectTotals();
            $this->cartRepository->save($quote);

            // 5. Place order
            $orderId = $this->cartManagement->placeOrder($quote->getId());
            if (!$orderId) {
                throw new LocalizedException(__('Order could not be created.'));
            }

            // TODO payplug SDK payment creation
            // $payplugPayment = $this->payplugHelper->createPayment()

            $response['error'] = false;
            $response['message'] = __('Order placed successfully.');
            $response['order_id'] = $orderId;
        } catch (\Exception $e) {
            $response['message'] = $e->getMessage();
        }

        return $result->setData($response);
    }

    /**
     * Update quote billing address with data from Apple Pay.
     */
    private function updateQuoteBillingAddress($quote, array $appleBilling): void
    {
        $billingAddress = $quote->getBillingAddress();

        // Apple Pay contact fields
        // $appleBilling = [
        //   "administrativeArea" => "CA",
        //   "country" => "United States",
        //   "countryCode" => "US",
        //   "familyName" => "Doe",
        //   "givenName" => "John",
        //   "locality" => "Los Angeles",
        //   "postalCode" => "90001",
        //   "addressLines" => ["1 Infinite Loop", "Cupertino"]
        //   "emailAddress" => "john@example.com",
        //   ...
        // ]

        $billingAddress->setFirstname($appleBilling['givenName'] ?? 'ApplePay')
            ->setLastname($appleBilling['familyName'] ?? 'Customer')
            ->setCity($appleBilling['locality'] ?? 'Unknown')
            ->setPostcode($appleBilling['postalCode'] ?? '')
            ->setRegion($appleBilling['administrativeArea'] ?? '')
            ->setCountryId($appleBilling['countryCode'] ?? 'US')
            ->setTelephone('0000000000');

        if (!empty($appleBilling['addressLines']) && is_array($appleBilling['addressLines'])) {
            $billingAddress->setStreet($appleBilling['addressLines']);
        } else {
            $billingAddress->setStreet(['Apple Pay Address']);
        }

        if (!empty($appleBilling['emailAddress'])) {
            $billingAddress->setEmail($appleBilling['emailAddress']);
        } else {
            // Fallback email if not provided
            $billingAddress->setEmail('guest@applepay.com');
        }
    }

    /**
     * Update shipping with final data from Apple Pay
     */
    private function updateQuoteShippingAddress($quote, array $appleShipping): void
    {
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setFirstname($appleShipping['givenName'] ?? 'ApplePay')
            ->setLastname($appleShipping['familyName'] ?? 'Customer')
            ->setCity($appleShipping['locality'] ?? 'Unknown')
            ->setPostcode($appleShipping['postalCode'] ?? '')
            ->setRegion($appleShipping['administrativeArea'] ?? '')
            ->setCountryId($appleShipping['countryCode'] ?? 'US')
            ->setTelephone('0000000000');

        if (!empty($appleShipping['addressLines']) && is_array($appleShipping['addressLines'])) {
            $shippingAddress->setStreet($appleShipping['addressLines']);
        } else {
            $shippingAddress->setStreet(['Apple Pay Shipping']);
        }
    }
}
