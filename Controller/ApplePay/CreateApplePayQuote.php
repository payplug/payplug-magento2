<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\ApplePay;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObject;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteManagement;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Payplug\Payments\Logger\Logger;

class CreateApplePayQuote implements HttpPostActionInterface
{
    public function __construct(
        private JsonFactory $resultJsonFactory,
        private QuoteFactory $quoteFactory,
        private CheckoutSession $checkoutSession,
        private ProductRepositoryInterface $productRepository,
        private Validator $formKeyValidator,
        private RequestInterface $request,
        private Logger $logger
    ) {
    }

    //TODO WIP it does not create the quote yet
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
                ->setCheckoutMethod(QuoteManagement::METHOD_GUEST)
                ->setCustomerIsGuest(true)
                ->setCustomerEmail('applepay@guest.com');

            $buyRequest = new DataObject(['qty' => $qty]);
            $resultAdd = $quote->addProduct($product, $buyRequest);

            if (is_string($resultAdd)) {
                throw new LocalizedException(__($resultAdd));
            }

            $quote->collectTotals();
            $this->checkoutSession->replaceQuote($quote);

            return $result->setData([
                'success' => true,
                'message' => 'Quote created',
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'error' => true,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
