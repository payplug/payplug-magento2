<?php

namespace Payplug\Payments\Block\ApplePay;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class ProductButton extends AbstractButton
{
    private ?ProductInterface $currentProduct = null;

    public function _toHtml(): string
    {
        $this->setCurrentProduct();

        if ($this->getRequest()->getFullActionName() === 'catalog_product_view'
            && $this->applePayHelper->canDisplayApplePay()
            && $this->applePayHelper->canDisplayApplePayOnProduct()
            && $this->getCurrentProduct()
        ) {
            return parent::_toHtml();
        }

        return '';
    }

    private function setCurrentProduct(): void
    {
        $productId = $this->getRequest()->getParam('id');

        if ($productId) {
            try {
                $storeId = $this->storeManager->getStore()->getId();
                $this->currentProduct = $this->productRepository->getById($productId, false, $storeId);
            } catch (NoSuchEntityException) {
            }
        }
    }

    public function getCurrentProduct(): ?ProductInterface
    {
        return $this->currentProduct;
    }
}
