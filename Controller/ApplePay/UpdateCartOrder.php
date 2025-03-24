<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\ApplePay;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Magento\Framework\App\Action\Action;

class UpdateCartOrder extends Action
{
    public function __construct(
        Context $context,
        private JsonFactory $resultJsonFactory,
        private Logger $logger,
        private Data $payplugHelper,
        private OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct($context);
    }

    /**
     * Update PayPlug Apple Pay transaction data from cart
     */
    public function execute(): Json
    {
        /** @var Json $response */
        $response = $this->resultJsonFactory->create();
        $response->setData([
            'error' => true,
            'message' => (string)__('An error occurred while processing the order.'),
        ]);

        try {
            $params = $this->getRequest()->getParams();
            $orderId = $params['order_id'];
            $order = $this->orderRepository->get($orderId);

            /*
             *
    [billing] => Array
        (
            [addressLines] => Array
                (
                    [0] => 8 Rue Cadet
                )

            [administrativeArea] =>
            [country] => France
            [countryCode] => FR
            [familyName] => Bouchier
            [givenName] => Tristan
            [locality] => Paris
            [phoneticFamilyName] =>
            [phoneticGivenName] =>
            [postalCode] => 75009
            [subAdministrativeArea] =>
            [subLocality] =>
        )

    [shipping] => Array
        (
            [addressLines] => Array
                (
                    [0] => 6 Rue Stéphane Mony
                )

            [administrativeArea] =>
            [country] => France
            [countryCode] => FR
            [emailAddress] => sheyne+payplugtest@dnd.fr
            [familyName] => Bouchier
            [givenName] => Tristan
            [locality] => Saint-Germain-en-Laye
            [phoneNumber] => 0640235908
            [phoneticFamilyName] =>
            [phoneticGivenName] =>
            [postalCode] => 78100
            [subAdministrativeArea] =>
            [subLocality] =>
        )

)
             */

            if (!$orderId || !$order) {
                throw new \Exception('Could not retrieve order');
            }

            $token = $this->getRequest()->getParam('token');
            if (empty($token)) {
                throw new \Exception('Could not retrieve token');
            }

            //TODO change the billing and shipping address and shipping method and also re-calculate taxes

            $payplugPayment = $this->payplugHelper->getOrderPayment($order->getIncrementId());
            $updatedPayment = $payplugPayment->update([
                'apple_pay' => [
                    'payment_token' => $token,
                ],
            ]);

            //TODO Check why it is not paid here.
            $this->logger->info(print_r($updatedPayment, true));


            //Should probably remove it later and move it to after the apple pay has been really paid
            if ($updatedPayment->is_paid) {
                $response->setData([
                    'error' => false,
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
}
