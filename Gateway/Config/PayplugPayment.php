<?php

namespace Payplug\Payments\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config as PayplugConfig;

class PayplugPayment extends \Magento\Payment\Gateway\Config\Config
{
    /**
     * @var PayplugConfig
     */
    private $payplugConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param PayplugConfig        $payplugConfig
     * @param string|null          $methodCode
     * @param string               $pathPattern
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        PayplugConfig $payplugConfig,
        $methodCode = null,
        $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);

        $this->payplugConfig = $payplugConfig;
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
     * @param int|null $storeId
     *
     * @return mixed
     */
    public function getValue($field, $storeId = null)
    {
        if ($field === 'order_place_redirect_url') {
            // Prevent order email sending when placing the order
            return true;
        }

        if ($field === 'payment_action') {
            $paymentAction = \Magento\Payment\Model\Method\AbstractMethod::ACTION_AUTHORIZE_CAPTURE;
            if ($this->payplugConfig->getConfigValue('invoice_on_payment', ScopeInterface::SCOPE_STORE, $storeId)) {
                $paymentAction = \Magento\Payment\Model\Method\AbstractMethod::ACTION_ORDER;
            }

            return $paymentAction;
        }

        return parent::getValue($field, $storeId);
    }
}
