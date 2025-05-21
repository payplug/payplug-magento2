<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\ApplePay;

use Exception;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;

class CreateApplePayQuote implements HttpPostActionInterface
{
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly Validator $formKeyValidator,
        private readonly RequestInterface $request,
        private readonly CartRepositoryInterface $cartRepository
    ) {
    }

    /**
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @throws LocalizedException
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setData(['error' => true, 'message' => 'Invalid form key.']);
        }

        $params = $this->request->getParams();
        $productId = (int)($params['product'] ?? 0);
        $qty = (float)($params['qty'] ?? 1);

        // TODO bundle product parameters
        // TODO grouped product parameters
        // TODO configurable parameters ?
        // TODO Custom options ?

        try {
            $quote = $this->checkoutSession->getQuote();
            $quote->removeAllItems();

            $product = $this->productRepository->getById($productId);
            $buyRequest = new DataObject(['qty' => $qty]);
            $resultAdd = $quote->addProduct($product, $buyRequest);

            if (is_string($resultAdd)) {
                throw new LocalizedException(__($resultAdd));
            }

            $this->cartRepository->save($quote);

            if ($quote->isVirtual() === false) {
                $shippingAddress = $quote->getShippingAddress();
                $shippingAddress->setShippingMethod('');
                $shippingAddress->setCollectShippingRates(true)->collectShippingRates();
            }

            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();

            $this->cartRepository->save($quote);

            return $result->setData([
                'success' => true,
                'message' => 'Quote created',
                'base_amount' => (float)$quote->getGrandTotal(),
                'is_virtual' => $quote->isVirtual()
            ]);
        } catch (Exception $e) {
            return $result->setData([
                'error' => true,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
