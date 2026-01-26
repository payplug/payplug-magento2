<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Block\ApplePay;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class ProductButton extends AbstractButton
{
    /**
     * @var array
     */
    private array $productCache = [];

    /**
     * Return button HTML
     *
     * @return string
     */
    public function _toHtml(): string
    {
        if ($this->getRequest()->getFullActionName() === 'catalog_product_view'
            && $this->applePayHelper->canDisplayApplePay()
            && $this->applePayHelper->canDisplayApplePayOnProduct()
            && $this->getCurrentProduct()
        ) {
            return parent::_toHtml();
        }

        return '';
    }

    /**
     * Return current product
     *
     * @return ProductInterface|null
     */
    public function getCurrentProduct(): ?ProductInterface
    {
        $productId = (int) $this->getRequest()->getParam('id');

        if (!$productId) {
            return null;
        }

        if (array_key_exists($productId, $this->productCache)) {
            return $this->productCache[$productId];
        }

        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
            $product = $this->productRepository->getById($productId, false, $storeId);
        } catch (NoSuchEntityException) {
            $product = null;
        }

        return $this->productCache[$productId] = $product;
    }
}
