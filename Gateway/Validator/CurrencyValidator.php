<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Gateway\Validator;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Payplug\Payments\Gateway\Config\Standard;
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
     * @param StoreManagerInterface $storeManager
     * @param ResultInterfaceFactory $resultFactory
     * @param ConfigInterface $config
     * @param Config $payplugConfig
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
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
     * @throws NoSuchEntityException
     */
    public function validate(array $validationSubject)
    {
        $storeId = (int) $validationSubject['storeId'];
        $websiteId = (int) $this->storeManager->getStore($storeId)->getWebsiteId();

        $isHostedFieldActive = $this->payplugConfig->isHostedFieldsActive($websiteId);

        if ($this->config instanceof Standard && $isHostedFieldActive === true) {
            /** Allow multi-currency for Hosted fields */
            return $this->createResult(
                true,
                ['status' => 200]
            );
        }

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
