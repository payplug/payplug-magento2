<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\ApplePay;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderAddressRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Exception\PayplugException;

class UpdateCartOrder implements HttpPostActionInterface
{
    public function __construct(
        private RequestInterface $request,
        private JsonFactory $resultJsonFactory,
        private Logger $logger,
        private Data $payplugHelper,
        private OrderRepositoryInterface $orderRepository,
        private OrderAddressRepositoryInterface $orderAddressRepository
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

        try {
            $params = $this->request->getParams();
            $orderId = $params['order_id'] ?? null;
            $token = $params['token'] ?? null;

            if (!$orderId || !$token) {
                throw new \Exception('Missing order_id or token parameter.');
            }

            $applePayBilling = $params['billing'] ?? [];
            $applePayShipping = $params['shipping'] ?? [];

            $order = $this->orderRepository->get($orderId);
            if (!$order || !$order->getId()) {
                throw new \Exception('Could not retrieve valid order.');
            }

            $this->updateOrderAddresses($order, $applePayBilling, $applePayShipping);

            $payplugPayment = $this->payplugHelper->getOrderPayment($order->getIncrementId());
            $updatedPayment = $payplugPayment->update([
                'apple_pay' => [
                    'payment_token' => $token,
                ],
            ]);

            $this->logger->info(print_r($updatedPayment, true));

            if ($updatedPayment->is_paid) {
                $response->setData([
                    'error' => false,
                    'message' => 'Apple Pay Payment is paid.',
                ]);
            } else {
                $response->setData([
                    'error' => false,
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
     * Update the Order's billing/shipping addresses
     */
    private function updateOrderAddresses(OrderInterface $order, array $applePayBilling, array $applePayShipping): void
    {
        $billingAddress = $order->getBillingAddress();
        if ($billingAddress) {
            $this->fillAddressData($billingAddress, $applePayBilling);
            $this->orderAddressRepository->save($billingAddress);
        }

        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress) {
            $this->fillAddressData($shippingAddress, $applePayShipping);
            $this->orderAddressRepository->save($shippingAddress);
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

        $address->setFirstname($firstname)
            ->setLastname($lastname)
            ->setStreet($street)
            ->setCity($city)
            ->setPostcode($postcode)
            ->setCountryId($countryId)
            ->setTelephone($telephone);

        if (isset($applePayData['emailAddress']) && method_exists($address, 'setEmail')) {
            $address->setEmail($applePayData['emailAddress']);
        }
    }
}
