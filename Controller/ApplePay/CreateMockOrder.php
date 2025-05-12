<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\ApplePay;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\AbstractMessage;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Quote\Model\QuoteFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Api\OrderRepositoryInterface;
use Payplug\Payments\Gateway\Config\ApplePay;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Service\GetQuoteApplePayAvailableMethods;

class CreateMockOrder implements HttpGetActionInterface
{
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CartManagementInterface $cartManagement,
        private readonly Logger $logger,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly QuoteFactory $quoteFactory,
        private readonly Validator $formKeyValidator,
        private readonly RequestInterface $request,
        private readonly GetQuoteApplePayAvailableMethods $getCurrentQuoteAvailableMethods
    ) {
    }

    /**
     * Create a mock order from a cart to pay with ApplePay.
     * Callable from a GET
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();
        $response = [
            'error' => true,
            'message' => __('An error occurred while processing the order.'),
        ];

        $formKeyValidation = $this->formKeyValidator->validate($this->request);
        if (!$formKeyValidation) {
            return $result->setData($response);
        }
        try {
            $sessionQuote = $this->checkoutSession->getQuote();
            if (!$sessionQuote || !$sessionQuote->getId()) {
                throw new LocalizedException(__('No active quote found.'));
            }
            $quote = $this->createNewGuestQuoteFromSession($sessionQuote);
            $placeholderAddress = [
                'givenName' => 'ApplePay',
                'familyName' => 'Customer',
                'locality' => 'Placeholder City',
                'postalCode' => '00000',
                'administrativeArea' => 'Placeholder Region',
                'countryCode' => 'US',
            ];

            $this->updateQuoteBillingAddress($quote, $placeholderAddress);
            $this->updateQuoteShippingAddress($quote, $placeholderAddress);

            $quote->setPaymentMethod(ApplePay::METHOD_CODE);
            $payment = $quote->getPayment();
            $payment->setMethod(ApplePay::METHOD_CODE);

            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setCollectShippingRates(true)->collectShippingRates();
            $quote->reserveOrderId();
            $quote->collectTotals();
            $this->cartRepository->save($quote);

            $availableShippingMethods = $this->getCurrentQuoteAvailableMethods->execute((int)$quote->getId());

            if (count($availableShippingMethods) == 0) {
                throw new LocalizedException(__('No available shipping methods.'));
            }

            $firstAvailableShippingMethod = $availableShippingMethods[0]['identifier'];

            $shippingAddress->setShippingMethod($firstAvailableShippingMethod);
            $this->cartRepository->save($quote);

            $orderId = null;
            try {
                $orderId = $this->cartManagement->placeOrder($quote->getId());
            } catch (\Throwable $e) {
                $this->logger->critical('placeOrder failed', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $response['message'] = $e->getMessage();
            }

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

            $this->logger->info(sprintf("%s %s", $e->getMessage(), $e->getTraceAsString()));
            $response['message'] = $e->getMessage();
        }

        return $result->setData($response);
    }

    /**
     * Creates a new quote as a guest from the items in the session quote.
     * This ensures that we do not reuse any existing address IDs from the previous quote
     * which can lead to "invalid address id" errors for guest checkouts.
     */
    private function createNewGuestQuoteFromSession(Quote $sessionQuote): Quote
    {
        /** @var Quote $newQuote */
        $newQuote = $this->quoteFactory->create();

        $storeId = $sessionQuote->getStoreId();
        $newQuote->setStoreId($storeId);

        $newQuote->setIsActive(true);
        $newQuote->setCheckoutMethod(QuoteManagement::METHOD_GUEST);
        $newQuote->setCustomerIsGuest(true);
        $newQuote->setCustomerEmail('placeholder@applepay.com');

        foreach ($sessionQuote->getAllVisibleItems() as $item) {
            $product = $item->getProduct();
            $buyRequest = $item->getBuyRequest();

            if (!$buyRequest) {
                $this->logger->critical('Missing buyRequest on item', ['sku' => $product->getSku()]);
                continue;
            }

            $result = $newQuote->addProduct($product, $buyRequest);
            if (is_string($result) || $result instanceof AbstractMessage) {
                $this->logger->critical('Failed to add product to quote', [
                    'product_id' => $product->getId(),
                    'sku' => $product->getSku(),
                    'message' => (string)$result
                ]);
            }
        }

        if (count($newQuote->getAllVisibleItems()) === 0) {
            throw new LocalizedException(__('No items could be added to the quote.'));
        }

        return $newQuote;
    }

    private function updateQuoteBillingAddress(CartInterface $quote, array $appleBilling): void
    {
        $billingAddress = $quote->getBillingAddress();
        $billingAddress->setCustomerAddressId(null);

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
        $shippingAddress->setCustomerAddressId(null);

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
