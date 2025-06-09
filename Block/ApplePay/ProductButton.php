<?php

namespace Payplug\Payments\Block\ApplePay;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class ProductButton extends AbstractButton
{
    private ?ProductInterface $currentProduct = null;

    public function _toHtml(): string
    {
        if ($this->applePayHelper->canDisplayApplePay()
            && $this->applePayHelper->canDisplayApplePayOnProduct()
            && $this->getCurrentProduct()
        ) {
            return parent::_toHtml();
        }

        return '';
    }

    public function getCurrentProduct(): ?ProductInterface
    {
        if ($this->getRequest()->getFullActionName() !== 'catalog_product_view') {
            return null;
        }

        if ($this->currentProduct === null) {
            $productId = $this->getRequest()->getParam('id');

            try {
                $storeId = $this->storeManager->getStore()->getId();
                $product = $this->productRepository->getById($productId, false, $storeId);
            } catch (NoSuchEntityException) {
                $product = null;
            }

            $this->currentProduct = $product;
        }

        return $this->currentProduct;
    }
}
