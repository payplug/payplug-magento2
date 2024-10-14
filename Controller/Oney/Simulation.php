<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\Oney;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\ConfigurableProduct\Api\LinkManagementInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\LayoutInterface;
use Payplug\Payments\Block\Oney\Simulation as OneySimulationBlock;
use Payplug\Payments\Logger\Logger;

class Simulation extends Action
{
    public function __construct(
        Context $context,
        private JsonFactory $resultJsonFactory,
        private ProductFactory $productFactory,
        private LayoutInterface $layout,
        private LinkManagementInterface $linkManagement
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        try {
            $params = $this->getRequest()->getParams();
            $productPrice = null;
            $product = $this->getProduct($params);
            $qty = null;
            if ($product !== null) {
                $qty = $params['qty'] ?? 1;
                $qty = (int)$qty;

                $productPrice = $product->getFinalPrice($qty);
                $productPrice = $productPrice * $qty;
            }

            $template = 'Payplug_Payments::oney/simulation_content.phtml';
            if (isset($params['wrapper'])) {
                $template = 'Payplug_Payments::oney/simulation.phtml';
            }

            $block = $this->layout->createBlock(OneySimulationBlock::class)
                ->setTemplate($template)
                ->setAmount($productPrice)
                ->setQty($qty);

            $result->setData([
                'success' => true,
                'html' => $block->toHtml(),
            ]);
        } catch (\Exception $e) {
            $result->setData(['success' => false]);
        }

        return $result;
    }

    /**
     * Get product for oney simulation
     *
     * @throws \Exception
     */
    private function getProduct(?array $params): ?Product
    {
        if (!isset($params['product'])) {
            return null;
        }

        $product = $this->productFactory->create();
        $product->load($params['product']);
        if (!$product->getId()) {
            throw new \Exception('Product not found');
        }

        if (!isset($params['product_options']) ||
            !is_array($params['product_options']) ||
            count($params['product_options']) === 0
        ) {
            return $product;
        }

        $productOptions = $params['product_options'];
        $attributes = [];
        foreach ($productOptions as $productOption) {
            $attributeName = $productOption['attribute'] ?? '';
            $attributeValue = $productOption['value'] ?? '';
            if (empty($attributeName) || empty($attributeValue)) {
                return $product;
            }
            $attributes[] = [
                'name' => $attributeName,
                'value' => $attributeValue,
            ];
        }
        $simpleProducts = $this->linkManagement->getChildren($product->getSku());
        foreach ($simpleProducts as $simpleProduct) {
            $loadedSimpleProduct = $this->productFactory->create();
            $loadedSimpleProduct->load($simpleProduct->getId());
            if (!$product->getId()) {
                continue;
            }
            foreach ($attributes as $attribute) {
                if ($loadedSimpleProduct->getData($attribute['name']) != $attribute['value']) {
                    continue 2;
                }
            }

            return $loadedSimpleProduct;
        }

        return $product;
    }
}
