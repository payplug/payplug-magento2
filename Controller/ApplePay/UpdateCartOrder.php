<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\ApplePay;

use Magento\Customer\Model\SessionFactory as CustomerSessionFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote\Address\ToOrder as QuoteAddressToOrder;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderAddressRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Exception\PayplugException;
use Magento\Framework\Locale\Resolver as LocaleResolver;

class UpdateCartOrder implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly Logger $logger,
        private readonly Data $payplugHelper,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderAddressRepositoryInterface $orderAddressRepository,
        private readonly LocaleResolver $localeResolver,
        private readonly Validator $formKeyValidator,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly DataObjectHelper $dataObjectHelper,
        private readonly QuoteAddressToOrder $quoteAddressToOrder,
        private readonly CustomerSessionFactory $customerSessionFactory
    ) {
    }

    /**
     * Update Apple Pay transaction data and addresses from order from POST resquests
     */
    public function execute(): Json
    {
        $this->logger->info('UpdateCartOrder');

        $response = $this->resultJsonFactory->create();
        $response->setData([
            'error' => true,
            'message' => (string)__('An error occurred while processing the order.'),
        ]);

        $formKeyValidation = $this->formKeyValidator->validate($this->request);
        if (!$formKeyValidation) {
            return $response;
        }

        try {
            $params = $this->request->getParams();
            $orderId = $params['order_id'] ?? null;
            $token = $params['token'] ?? null;
            $selectedShippingMethod = $params['shipping_method'] ?? null;
            $workflowType = $params['workflowType'] ?? null;

            if (!$orderId || !$token) {
                throw new \Exception('Missing order_id or token parameter.');
            }

            $applePayBilling = $params['billing'] ?? [];
            $applePayShipping = $params['shipping'] ?? [];

            $order = $this->orderRepository->get($orderId);
            if (!$order || !$order->getId()) {
                throw new \Exception('Could not retrieve valid order.');
            }

            if ($selectedShippingMethod) {
                $this->updateOrderShippingMethod($order, $selectedShippingMethod);
            }

            $this->updateOrderAddresses($order, $applePayBilling, $applePayShipping);

            $payplugPayment = $this->payplugHelper->getOrderPayment($order->getIncrementId());
            /** @var Order $order */
            $paymentObject = $payplugPayment->retrieve(
                $payplugPayment->getScopeId($order),
                $payplugPayment->getScope($order)
            );
            $metadatas = $paymentObject->metadata;
            $metadatas['ApplepayWorkflowType'] = $workflowType;

            $updatedPayment = $paymentObject->update([
                'apple_pay' => [
                    'payment_token' => $token,
                    'billing' => $this->getPayplugAddressArray($order, true),
                    'shipping' => $this->getPayplugAddressArray($order, false),
                    'amount' => (int)($order->getGrandTotal() * 100),
                ],
                'metadata' => $metadatas
            ]);

            if ($updatedPayment->is_paid) {
                $response->setData([
                    'error' => false,
                    'message' => 'Apple Pay Payment is paid.',
                ]);
            } else {
                $response->setData([
                    'error' => true,
                    'message' => 'Apple Pay Payment updated but not paid yet.',
                ]);
            }

            return $response;
        } catch (PayplugException $e) {
            $this->logger->error('PayplugException: Could not update apple pay transaction', [
                'message' => $e->__toString(),
                'exception' => $e,
            ]);
            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Exception: Could not update apple pay transaction', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            return $response;
        }
    }

    /**
     * Convert an order address to a payplug address array
     */
    public function getPayplugAddressArray(OrderInterface $order, bool $isBilling): array
    {
        /** @var OrderAddressInterface|null $address */
        $address = $isBilling ? $order->getBillingAddress() : $order->getShippingAddress();
        if (!$address) {
            throw new \RuntimeException(
                $isBilling
                    ? __('No billing address found for this order.') . toString()
                    : __('No shipping address found for this order.') . toString()
            );
        }

        $title = $address->getPrefix() ?: null;
        $streetAll = $address->getStreet();
        $streetLine = is_array($streetAll) ? implode(', ', $streetAll) : (string)$streetAll;
        $deliveryType = $isBilling ? 'BILLING' : 'NEW';
        $localeCode = $this->localeResolver->getLocale();
        $language = substr($localeCode, 0, 2);

        $formattedAddress = [
            'title' => $title,
            'first_name' => $address->getFirstname() ?: '',
            'last_name' => $address->getLastname() ?: '',
            'email' => $address->getEmail() ?: $order->getCustomerEmail() ?: '',
            'address1' => $streetLine,
            'postcode' => $address->getPostcode() ?: '',
            'city' => $address->getCity() ?: '',
            'country' => $address->getCountryId() ?: '',
            'language' => $language,
        ];

        if (!$isBilling) {
            $formattedAddress['delivery_type'] = $deliveryType;
        }

        return $formattedAddress;
    }

    /**
     * Update the Order's billing/shipping addresses
     */
    private function updateOrderAddresses(OrderInterface $order, array $applePayBilling, array $applePayShipping): void
    {
        $email = $applePayShipping['emailAddress'] ?? '';
        $phone = $applePayShipping['phoneNumber'] ?? '';
        $firstname = $applePayBilling['givenName'] ?? 'ApplePay';
        $lastname = $applePayBilling['familyName'] ?? 'Customer';

        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress) {
            $this->fillAddressData($shippingAddress, $applePayShipping);
            $this->orderAddressRepository->save($shippingAddress);
        }

        $billingAddress = $order->getBillingAddress();
        if ($billingAddress) {
            $this->fillAddressData($billingAddress, $applePayBilling);
            $billingAddress->setTelephone($phone);
            $billingAddress->setEmail($email);
            $this->orderAddressRepository->save($billingAddress);
        }

        $order->setCustomerEmail($email);
        $order->setCustomerFirstname($firstname);
        $order->setCustomerLastname($lastname);

        $customerSession = $this->customerSessionFactory->create();
        if ($customerSession->isLoggedIn()) {
            $customer = $customerSession->getCustomer();

            $order->setCustomerIsGuest(false);
            $order->setCustomerId($customer->getId());
            $order->setCustomerGroupId($customer->getGroupId());
        }

        $this->orderRepository->save($order);
    }

    /**
     * Fill an address object with Apple Pay data
     */
    private function fillAddressData(OrderAddressInterface $address, array $applePayData): void
    {
        $firstname = $applePayData['givenName'] ?? 'ApplePay';
        $lastname = $applePayData['familyName'] ?? 'Customer';
        $street = $applePayData['addressLines'] ?? ['Apple Pay Address'];
        $city = $applePayData['locality'] ?? 'Unknown';
        $postcode = $applePayData['postalCode'] ?? '00000';
        $countryId = $applePayData['countryCode'] ?? 'US';
        $telephone = $applePayData['phoneNumber'] ?? '0000000000';
        $region = $applePayData['administrativeArea'] ?? '';

        $address->setFirstname($firstname)
            ->setLastname($lastname)
            ->setStreet($street)
            ->setCity($city)
            ->setPostcode($postcode)
            ->setCountryId($countryId)
            ->setTelephone($telephone)
            ->setRegion($region);

        if (isset($applePayData['emailAddress']) && method_exists($address, 'setEmail')) {
            $address->setEmail($applePayData['emailAddress']);
        }
    }

    private function updateOrderShippingMethod(OrderInterface $order, string $selectedShippingMethod): void
    {
        $quoteId = $order->getQuoteId();

        try {
            $quote = $this->cartRepository->get($quoteId);
        } catch (NoSuchEntityException) {
            $this->logger->error('Could not retrieve quote for order');
            return;
        }

        $quote->getShippingAddress()->setShippingMethod($selectedShippingMethod);
        $quote->getShippingAddress()->setCollectShippingRates(true)->collectShippingRates();
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();

        $this->cartRepository->save($quote);

        $this->dataObjectHelper->mergeDataObjects(
            OrderInterface::class,
            $order,
            $this->quoteAddressToOrder->convert($quote->getShippingAddress())
        );

        $order->setShippingMethod($selectedShippingMethod);
        $this->orderRepository->save($order);
    }
}
