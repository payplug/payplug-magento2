<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Controller\Oney;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\ConfigurableProduct\Api\LinkManagementInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\LayoutInterface;
use Payplug\Payments\Block\Oney\Simulation as OneySimulationBlock;
use Payplug\Payments\ViewModel\Oney as OneyViewHelper;

class Simulation extends Action
{
    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param ProductFactory $productFactory
     * @param LayoutInterface $layout
     * @param LinkManagementInterface $linkManagement
     * @param OneyViewHelper $oneyViewHelper
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly ProductFactory $productFactory,
        private readonly LayoutInterface $layout,
        private readonly LinkManagementInterface $linkManagement,
        private readonly OneyViewHelper $oneyViewHelper
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
                ->setData('oneyViewHelper', $this->oneyViewHelper)
                ->setAmount($productPrice)
                ->setQty($qty);

            $result->setData([
                'success' => true,
                'html' => $block->toHtml(),
            ]);
        } catch (Exception) {
            $result->setData(['success' => false]);
        }

        return $result;
    }

    /**
     * Get product for oney simulation
     *
     * @param array|null $params
     * @return Product|null
     * @throws LocalizedException
     */
    private function getProduct(?array $params): ?Product
    {
        if (!isset($params['product'])) {
            return null;
        }

        $product = $this->productFactory->create();
        $product->load($params['product']);
        if (!$product->getId()) {
            throw new LocalizedException(__('Product not found'));
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
