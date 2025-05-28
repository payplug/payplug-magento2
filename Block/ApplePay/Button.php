<?php

declare(strict_types=1);

namespace Payplug\Payments\Block\ApplePay;

use Magento\Bundle\Model\Product\Type as BundleProductType;
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

class Button extends Template
{
    public function __construct(
        private readonly ApplePay $applePayHelper,
        private readonly ConfigProvider $configProvider,
        private readonly Json $jsonSerializer,
        private readonly StoreManagerInterface $storeManager,
        private readonly CheckoutSession $checkoutSession,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritdoc
     */
    public function _toHtml(): string
    {
        if ($this->applePayHelper->canDisplayApplePay() && $this->applePayHelper->canDisplayApplePayOnCart()) {
            return parent::_toHtml();
        }

        return '';
    }

    public function getConfig(): array
    {
        $config = $this->configProvider->getConfig();
        $applePayConfig = $config['payment'][ApplePayMethodCode::METHOD_CODE] ?? [];
        $applePayConfig['currency'] = $this->getCurrencyCode();

        return $applePayConfig;
    }

    public function getConfigJson(): string
    {
        return $this->jsonSerializer->serialize($this->getConfig());
    }

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

    private function getCurrencyCode(): string
    {
        return $this->storeManager->getStore()->getCurrentCurrencyCode();
    }
}
