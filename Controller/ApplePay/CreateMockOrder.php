<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\ApplePay;

use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Cart as CartModel;
use Magento\Checkout\Model\Cart\RequestQuantityProcessor;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filter\LocalizedToNormalized;
use Magento\Framework\Locale\ResolverInterface as LocaleResolverInterface;
use Magento\Framework\Message\MessageInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartInterfaceFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Payplug\Payments\Gateway\Config\ApplePay;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Service\GetQuoteApplePayAvailableMethods;
use Throwable;

class CreateMockOrder implements HttpPostActionInterface
{
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CartManagementInterface $cartManagement,
        private readonly Logger $logger,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CartInterfaceFactory $cartInterfaceFactory,
        private readonly Validator $formKeyValidator,
        private readonly RequestInterface $request,
        private readonly GetQuoteApplePayAvailableMethods $getCurrentQuoteAvailableMethods,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CartModel $cartModel,
        private readonly LocaleResolverInterface $localeResolver,
        private readonly RequestQuantityProcessor $requestQuantityProcessor,
        private readonly EventManagerInterface $eventManager,
        private readonly ResponseInterface $response
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
            $productId = (int)$this->request->getParam('product');

            if ($productId) {
                $storeId = $this->storeManager->getStore()->getId();
                $product = $this->productRepository->getById($productId, false, $storeId);

                $sessionQuote->removeAllItems();

                $params = $this->request->getParams();

                if (isset($params['qty'])) {
                    $filter = new LocalizedToNormalized(['locale' => $this->localeResolver->getLocale()]);
                    $params['qty'] = $this->requestQuantityProcessor->prepareQuantity($params['qty']);
                    $params['qty'] = $filter->filter($params['qty']);
                }

                $this->cartModel->addProduct($product, $params);

                if ($sessionQuote->isVirtual() === false) {
                    $sessionQuote->getShippingAddress()->setShippingMethod('');
                }

                $this->cartModel->save();

                $this->eventManager->dispatch(
                    'checkout_cart_add_product_complete',
                    ['product' => $product, 'request' => $this->request, 'response' => $this->response]
                );

                if ($sessionQuote->getHasError()) {
                    throw new LocalizedException(__('Order could not be created.'));
                }
            }

            if (!$sessionQuote || !$sessionQuote->getId()) {
                throw new LocalizedException(__('No active quote found.'));
            }

            $newQuote = $this->createNewGuestQuoteFromSession($sessionQuote);

            $placeholderAddress = [
                'givenName' => $this->getApplePayConfig('placeholder_firstname'),
                'familyName' => $this->getApplePayConfig('placeholder_lastname'),
                'locality' => $this->getApplePayConfig('placeholder_postcode'),
                'postalCode' => $this->getApplePayConfig('placeholder_city'),
                'administrativeArea' => $this->getApplePayConfig('placeholder_region'),
                'countryCode' => $this->getApplePayConfig('default_country'),
            ];

            $this->updateQuoteBillingAddress($newQuote, $placeholderAddress);

            if ($newQuote->isVirtual() === false) {
                $this->updateQuoteShippingAddress($newQuote, $placeholderAddress);
                $newQuote->getShippingAddress()->setCollectShippingRates(true)->collectShippingRates();
                $this->cartRepository->save($newQuote);

                $availableShippingMethods = $this->getCurrentQuoteAvailableMethods->execute((int)$newQuote->getId());
                $firstAvailableShippingMethod = $availableShippingMethods[0]['identifier'] ?? '';
                $newQuote->getShippingAddress()->setShippingMethod($firstAvailableShippingMethod);
            }

            $newQuote->setPaymentMethod(ApplePay::METHOD_CODE);
            $payment = $newQuote->getPayment();
            $payment->setMethod(ApplePay::METHOD_CODE);

            $newQuote->reserveOrderId();
            $newQuote->collectTotals();
            $this->cartRepository->save($newQuote);

            $orderId = null;
            try {
                $orderId = $this->cartManagement->placeOrder($newQuote->getId());
            } catch (Throwable $e) {
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
                throw new Exception('Could not retrieve merchant session');
            }

            $grandTotal = (float)$order->getGrandTotal();
            $shippingAmount = (float)$order->getShippingAmount();

            $response['error'] = false;
            $response['message'] = __('Order placed successfully.');
            $response['order_id'] = $orderId;
            $response['base_amount'] = $grandTotal - $shippingAmount;
            $response['merchantSession'] = $merchantSession;
        } catch (Exception $e) {
            $this->logger->info(sprintf("%s %s", $e->getMessage(), $e->getTraceAsString()));
            $response['message'] = $e->getMessage();
        }

        return $result->setData($response);
    }

    private function getApplePayConfig(string $field): ?string
    {
        return $this->storeManager->getStore()->getConfig('payment/payplug_payments_apple_pay/' . $field);
    }

    /**
     * Creates a new quote as a guest from the items in the session quote.
     * This ensures that we do not reuse any existing address IDs from the previous quote
     * which can lead to "invalid address id" errors for guest checkouts.
     * @throws LocalizedException
     */
    private function createNewGuestQuoteFromSession(CartInterface $sessionQuote): CartInterface
    {
        $storeId = $sessionQuote->getStoreId();

        /**
         * Rebuild temporary quote to avoid interference with existing quote
         */
        $newQuote = $this->cartInterfaceFactory->create();
        $newQuote->setStoreId($storeId);
        $newQuote->setIsActive(true);
        $newQuote->setCheckoutMethod(CartManagementInterface::METHOD_GUEST);
        $newQuote->setCustomerIsGuest(true);
        $newQuote->setCustomerEmail($this->getApplePayConfig('placeholder_email'));

        foreach ($sessionQuote->getAllVisibleItems() as $item) {
            $product = $item->getProduct();
            $buyRequest = $item->getBuyRequest();

            if (!$buyRequest) {
                $this->logger->critical('Missing buyRequest on item', ['sku' => $product->getSku()]);
                continue;
            }

            $result = $newQuote->addProduct($product, $buyRequest);
            if (is_string($result) || $result instanceof MessageInterface) {
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
            ->setCountryId($appleBilling['countryCode'] ?? 'FR')
            ->setRegion(null)
            ->setRegionId(null)
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
            ->setCountryId($appleShipping['countryCode'] ?? 'FR')
            ->setRegion(null)
            ->setRegionId(null)
            ->setTelephone('0000000000')
            ->setShouldIgnoreValidation(true);

        $street = $appleShipping['addressLines'] ?? ['Apple Pay Shipping'];
        $shippingAddress->setStreet($street);
    }
}
