<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\ApplePay;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
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
        private RequestInterface $request,
        private JsonFactory $resultJsonFactory,
        private Logger $logger,
        private Data $payplugHelper,
        private OrderRepositoryInterface $orderRepository,
        private OrderAddressRepositoryInterface $orderAddressRepository,
        private LocaleResolver $localeResolver,
        private Validator $formKeyValidator
    ) {
    }

    /**
     * Update Apple Pay transaction data and addresses from order from POST resquests
     */
    public function execute(): Json
    {
        $this->logger->info('UpdateCartOrder');
        /** @var Json $response */
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
            $amount  = $params['amount'] ?? null;
            $workflowType = $params['workflowType'] ?? null;

            $this->logger->info(print_r($params, true));

            if (!$orderId || !$token || !$amount) {
                throw new \Exception('Missing order_id or token or amount parameter.');
            }

            $applePayBilling = $params['billing'] ?? [];
            $applePayShipping = $params['shipping'] ?? [];

            $order = $this->orderRepository->get($orderId);
            if (!$order || !$order->getId()) {
                throw new \Exception('Could not retrieve valid order.');
            }

            $this->updateOrderAddresses($order, $applePayBilling, $applePayShipping);

            $payplugPayment = $this->payplugHelper->getOrderPayment($order->getIncrementId());
            $paymentObject = $payplugPayment->retrieve($payplugPayment->getScopeId($order), $payplugPayment->getScope($order));
            $metadatas = $paymentObject->metadata;
            $metadatas['ApplepayWorkflowType'] = $workflowType;

            $updatedPayment = $paymentObject->update([
                'apple_pay' => [
                    'payment_token' => $token,
                    'billing' => $this->getPayplugAddressArray($order, true),
                    'shipping' => $this->getPayplugAddressArray($order, false),
                    'amount' => (int)($amount * 100),
                ],
                'metadata' => $metadatas
            ]);

            $this->logger->info(print_r($updatedPayment, true));

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
            $this->logger->error('Could not update apple pay transaction', [
                'message' => $e->__toString(),
                'exception' => $e,
            ]);
            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Could not update apple pay transaction', [
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

        $order->setCustomerEmail($email);
        $order->setCustomerFirstname($firstname);
        $order->setCustomerLastname($lastname);

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

        $this->orderRepository->save($order);

        $this->logger->info('Order addresses updated successfully.');
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
}
