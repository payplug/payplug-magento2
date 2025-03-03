<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\ApplePay;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Payplug\Payments\Helper\Data as PayplugHelper;
use Payplug\Payments\Logger\Logger;

class PlaceCartOrder extends Action
{
    public function __construct(
        Context $context,
        private JsonFactory $resultJsonFactory,
        private CheckoutSession $checkoutSession,
        private CartRepositoryInterface $cartRepository,
        private CartManagementInterface $cartManagement,
        private PayplugHelper $payplugHelper,
        private Logger $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $this->logger->info('--place order 0---');
        /** @var Json $result */
        $result = $this->resultJsonFactory->create();
        $response = [
            'merchand_data' => [],
            'error' => true,
            'message' => (string)__('An error occurred while processing the order.'),
        ];

        try {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote || !$quote->getId()) {
                throw new LocalizedException(__('No active quote found.'));
            }

            // 1. Grab Apple Pay data from request
            $this->logger->info('--place order 1---');

            $param = $this->getRequest()->getParam('event');
            $eventJson = base64_decode($param);
            $this->logger->info('$eventJson='.$eventJson);
            $event = json_decode($eventJson, true);
            $this->logger->info('$eventArray='.print_r($event, true));
            $validationUrl = $event['validationURL'];
            $this->logger->info('$validationUrl='.$validationUrl);

           /* $tokenJson = $this->getRequest()->getParam('token');
            $billingContact = $this->getRequest()->getParam('billingContact');
            $shippingContact = $this->getRequest()->getParam('shippingContact');

            if (!$tokenJson) {
                throw new LocalizedException(__('No Apple Pay token present.'));
            }

            // 2. Update billing address from Apple Pay data
            $this->logger->info('--place order 2---');
            if (is_array($billingContact)) {
                $this->updateQuoteBillingAddress($quote, $billingContact);
            }*/

            // 3. Set Payment method and store Apple Pay token or relevant data
            $this->logger->info('--place order 3---');
            $quote->setPaymentMethod('payplug_payments_apple_pay');
            $payment = $quote->getPayment();
            $payment->setMethod('payplug_payments_apple_pay');
        //  $payment->setAdditionalInformation('apple_pay_token', $tokenJson);

            // 4. Re-collect totals, save
            $this->logger->info('--place order 4---');
            $quote->collectTotals();
            $this->cartRepository->save($quote);

            // 5. Place order
            $this->logger->info('--place order 5---');
            $orderId = $this->cartManagement->placeOrder($quote->getId());
            if (!$orderId) {
                throw new LocalizedException(__('Order could not be created.'));
            }

            // TODO 6. payplug SDK payment creation
            $this->logger->info('--place order 6---');
            // $payplugPayment = $this->payplugHelper->createPayment()

            // TODO 7. get merchand session from payment data
            $this->logger->info('--place order 7---');
           // $merchandSession = $order->getPayment()->getAdditionalInformation('merchand_session');
           // $this->logger->info('--$merchandSession---'.print_r($merchandSession, true));
           // $order->getPayment()->unsAdditionalInformation('merchand_session');

            $response['error'] = false;
            $response['message'] = __('Order placed successfully.');
            $response['order_id'] = $orderId;
        } catch (\Exception $e) {
            $this->logger->info('--place order 7.5 error---'.$e->getMessage());
            $response['message'] = $e->getMessage();
        }

        $this->logger->info('--place order 8---'.print_r($response, true));
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
