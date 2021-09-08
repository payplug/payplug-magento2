<?php

namespace Payplug\Payments\Controller\Oney;

use Magento\Catalog\Model\ProductFactory;
use Magento\ConfigurableProduct\Api\LinkManagementInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\LayoutInterface;

class Simulation extends \Magento\Framework\App\Action\Action
{
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var LayoutInterface
     */
    private $layout;

    /**
     * @var LinkManagementInterface
     */
    private $linkManagement;

    /**
     * @param Context                 $context
     * @param JsonFactory             $resultJsonFactory
     * @param ProductFactory          $productFactory
     * @param LayoutInterface         $layout
     * @param LinkManagementInterface $linkManagement
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ProductFactory $productFactory,
        LayoutInterface $layout,
        LinkManagementInterface $linkManagement
    ) {
        parent::__construct($context);

        $this->resultJsonFactory = $resultJsonFactory;
        $this->productFactory = $productFactory;
        $this->layout = $layout;
        $this->linkManagement = $linkManagement;
    }

    /**
     * @inheritdoc
     */
    public function execute()
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

            $block = $this->layout->createBlock(\Payplug\Payments\Block\Oney\Simulation::class)
                ->setTemplate($template)
                ->setAmount($productPrice)
                ->setQty($qty)
            ;

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
     * @param array $params
     *
     * @return \Magento\Catalog\Model\Product|null
     *
     * @throws \Exception
     */
    private function getProduct($params)
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
