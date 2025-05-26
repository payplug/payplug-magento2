<?php

declare(strict_types=1);

namespace Payplug\Payments\Block\ApplePay;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Payplug\Payments\Helper\ApplePay;
use Payplug\Payments\Model\Payment\ApplePay\ConfigProvider;
use Payplug\Payments\Gateway\Config\ApplePay as ApplePayMethodCode;

class Button extends Template
{
    public function __construct(
        Context $context,
        private ApplePay $applePayHelper,
        private ConfigProvider $configProvider,
        private Json $jsonSerializer,
        private StoreManagerInterface $storeManager,
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

    private function getCurrencyCode(): string
    {
        return $this->storeManager->getStore()->getCurrentCurrencyCode();
    }
}
