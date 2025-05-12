<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\ApplePay;

use Exception;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObject;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Payplug\Payments\Logger\Logger;

class CreateApplePayQuote implements HttpPostActionInterface
{
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly QuoteFactory $quoteFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly Validator $formKeyValidator,
        private readonly RequestInterface $request,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly Logger $logger
    ) {
    }

    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setData(['error' => true, 'message' => 'Invalid form key.']);
        }

        $params = $this->request->getParams();
        $productId = (int)($params['product_id'] ?? 0);
        $qty = (float)($params['qty'] ?? 1);

        try {
            $product = $this->productRepository->getById($productId);

            $quote = $this->quoteFactory->create();
            $quote->setStoreId($product->getStoreId())
                ->setIsActive(true)
                ->setCheckoutMethod(CartManagementInterface::METHOD_GUEST)
                ->setCustomerIsGuest(true)
                ->setCustomerEmail('applepay@guest.com');

            $buyRequest = new DataObject(['qty' => $qty]);
            $resultAdd = $quote->addProduct($product, $buyRequest);

            if (is_string($resultAdd)) {
                throw new LocalizedException(__($resultAdd));
            }

            $fakeGuestAddress = [
                'firstname' => 'Guest',
                'lastname' => 'User',
                'street' => '123 Test Street',
                'city' => 'Paris',
                'postcode' => '75000',
                'telephone' => '0000000000',
                'country_id' => 'FR',
                'region' => 'Ile-de-France',
            ];

            $quote->getBillingAddress()->addData($fakeGuestAddress);
            $shippingAddress = $quote->getShippingAddress()->addData($fakeGuestAddress);

            $shippingAddress->setCollectShippingRates(true)->collectShippingRates();

            $quote->collectTotals();
            $this->cartRepository->save($quote);

            $this->checkoutSession->replaceQuote($quote);

            return $result->setData([
                'success' => true,
                'message' => 'Quote created',
                'base_amount' => $quote->getGrandTotal()
            ]);
        } catch (Exception $e) {
            return $result->setData([
                'error' => true,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
