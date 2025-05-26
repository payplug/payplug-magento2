<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\ApplePay;

use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Cart as CartModel;
use Magento\Checkout\Model\Cart\RequestQuantityProcessor;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filter\LocalizedToNormalized;
use Magento\Framework\Locale\ResolverInterface as LocaleResolverInterface;
use Magento\Store\Model\StoreManagerInterface;

class CreateApplePayQuote implements HttpPostActionInterface
{
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly RequestInterface $request,
        private readonly LocaleResolverInterface $localeResolver,
        private readonly RequestQuantityProcessor $requestQuantityProcessor,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CartModel $cartModel,
        private readonly ManagerInterface $eventManager,
        private readonly ResponseInterface $response
    ) {
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function execute(): Json
    {
        if (!$this->formKeyValidator->validate($this->request)) {
            return $this->getJsonErrorResult();
        }

        $params = $this->request->getParams();

        try {
            if (isset($params['qty'])) {
                $filter = new LocalizedToNormalized(['locale' => $this->localeResolver->getLocale()]);
                $params['qty'] = $this->requestQuantityProcessor->prepareQuantity($params['qty']);
                $params['qty'] = $filter->filter($params['qty']);
            }

            $storeId = $this->storeManager->getStore()->getId();

            $productId = (int)$this->request->getParam('product');
            $product = $this->productRepository->getById($productId, false, $storeId);

            if (!($product instanceof Product)) {
                return $this->getJsonErrorResult();
            }

            $quote = $this->cartModel->getQuote();

            $quote->removeAllItems();
            $this->cartModel->addProduct($product, $params);

            $related = $this->request->getParam('related_product');

            if (!empty($related)) {
                $this->cartModel->addProductsByIds(explode(',', $related));
            }

            if ($quote->isVirtual() === false) {
                $quote->getShippingAddress()->setShippingMethod('');
            }

            $this->cartModel->save();

            $this->eventManager->dispatch(
                'checkout_cart_add_product_complete',
                ['product' => $product, 'request' => $this->request, 'response' => $this->response]
            );

            if ($quote->getHasError()) {
                $message = '';
                foreach ($quote->getErrors() as $error) {
                    $message .= $error->getText();
                }

                return $this->getJsonErrorResult($message);
            }
        } catch (LocalizedException $e) {
            return $this->getJsonErrorResult($e->getMessage());
        } catch (Exception) {
            return $this->getJsonErrorResult();
        }

        return $this->resultJsonFactory->create()->setData([
            'success' => true,
            'message' => 'Quote created',
            'base_amount' => (float)$quote->getGrandTotal(),
            'is_virtual' => $quote->isVirtual()
        ]);
    }

    private function getJsonErrorResult(string $message = null): Json
    {
        $message = $message ? __($message) : __('An error occurred while adding product.');

        $jsonResult = $this->resultJsonFactory->create();
        $jsonResult->setData(['error' => true, 'message' => $message]);

        return $jsonResult;
    }
}
