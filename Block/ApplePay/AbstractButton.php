<?php

declare(strict_types=1);

namespace Payplug\Payments\Block\ApplePay;

use Magento\Bundle\Model\Product\Type as BundleProductType;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Payplug\Payments\Gateway\Config\ApplePay as ApplePayMethodCode;
use Payplug\Payments\Helper\ApplePay;
use Payplug\Payments\Model\Payment\ApplePay\ConfigProvider;

abstract class AbstractButton extends Template
{
    /**
     * @param ConfigProvider $configProvider
     * @param Json $jsonSerializer
     * @param StoreManagerInterface $storeManager
     * @param CheckoutSession $checkoutSession
     * @param ApplePay $applePayHelper
     * @param ProductRepositoryInterface $productRepository
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly Json $jsonSerializer,
        protected readonly StoreManagerInterface $storeManager,
        private readonly CheckoutSession $checkoutSession,
        protected readonly ApplePay $applePayHelper,
        protected readonly ProductRepositoryInterface $productRepository,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get ApplePay configuration
     *
     * @return array
     */
    public function getConfig(): array
    {
        $config = $this->configProvider->getConfig();
        $applePayConfig = $config['payment'][ApplePayMethodCode::METHOD_CODE] ?? [];
        $applePayConfig['currency'] = $this->getCurrencyCode();

        return $applePayConfig;
    }

    /**
     * Get ApplePay configuration as JSON
     *
     * @return string
     */
    public function getConfigJson(): string
    {
        return $this->jsonSerializer->serialize($this->getConfig());
    }

    /**
     * Check if quote has bundle product item
     *
     * @return bool
     */
    public function quoteHasBundleProductItem(): bool
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            $items = $quote->getAllVisibleItems();
            foreach ($items as $item) {
                if ($item->getProductType() === BundleProductType::TYPE_CODE) {
                    return true;
                }
            }
        } catch (NoSuchEntityException|LocalizedException) {
            return false;
        }

        return false;
    }

    /**
     * Get currency code
     *
     * @return string
     * @throws NoSuchEntityException
     */
    private function getCurrencyCode(): string
    {
        return $this->storeManager->getStore()->getCurrentCurrencyCode();
    }
}
