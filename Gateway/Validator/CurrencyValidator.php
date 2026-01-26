<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config;

class CurrencyValidator extends AbstractValidator
{
    /**
     * @var \Magento\Payment\Gateway\ConfigInterface
     */
    private $config;

    /**
     * @var Config
     */
    private $payplugConfig;

    /**
     * CurrencyValidator constructor.
     *
     * @param ResultInterfaceFactory $resultFactory
     * @param ConfigInterface        $config
     * @param Config                 $payplugConfig
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        ConfigInterface $config,
        Config $payplugConfig
    ) {
        $this->payplugConfig = $payplugConfig;
        $this->config = $config;
        parent::__construct($resultFactory);
    }

    /**
     * Validate currency for PayPlug payment
     *
     * @param array $validationSubject
     *
     * @return \Magento\Payment\Gateway\Validator\ResultInterface
     */
    public function validate(array $validationSubject)
    {
        $allowedCurrency = $this->payplugConfig->getConfigValue(
            'currencies',
            ScopeInterface::SCOPE_STORE,
            $validationSubject['storeId']
        );

        if ($allowedCurrency == $validationSubject['currency']) {
            return $this->createResult(
                true,
                ['status' => 200]
            );
        }

        return $this->createResult(
            false,
            [__('The currency selected is not supported by Payplug Payments.')]
        );
    }
}
