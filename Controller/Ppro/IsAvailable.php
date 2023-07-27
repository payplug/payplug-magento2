<?php

namespace Payplug\Payments\Controller\Ppro;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Phrase;
use Payplug\Payments\Gateway\Config\Giropay;
use Payplug\Payments\Gateway\Config\Ideal;
use Payplug\Payments\Gateway\Config\Mybank;
use Payplug\Payments\Gateway\Config\Satispay;
use Payplug\Payments\Gateway\Config\Sofort;
use Payplug\Payments\Helper\Config;

class IsAvailable extends \Magento\Framework\App\Action\Action
{
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @param Context     $context
     * @param JsonFactory $resultJsonFactory
     * @param Config      $configHelper
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Config $configHelper
    ) {
        parent::__construct($context);

        $this->resultJsonFactory = $resultJsonFactory;
        $this->configHelper = $configHelper;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $result->setData([
            'success' => true,
        ]);

        try {
            if ($this->configHelper->getIsSandbox()) {
                $result->setData([
                    'success' => false,
                    'data' => ['message' => __('The payment is not available for the TEST mode.')]
                ]);
            } else {
                $params = $this->getRequest()->getParams();
                $country = $params['billingCountry'] ?: $params['shippingCountry'];
                $method = str_replace('payplug_payments_', '', $params['paymentMethod']);
                $countries = json_decode(
                    $this->configHelper->getConfigValue($method . '_countries'),
                    true
                );
                if (!is_array($countries) || !in_array($country, $countries)) {
                    $result->setData([
                        'success' => false,
                        'data' => ['message' => $this->getCountryErrorMessage($params['paymentMethod'])],
                    ]);
                }
            }
        } catch (\Exception $e) {
            $result->setData([
                'success' => false,
                'data' => ['message' => __('An error occurred. Please try again.')]
            ]);
        }

        return $result;
    }

    /**
     * @param string $method
     *
     * @return Phrase
     *
     * @throws \Exception
     */
    private function getCountryErrorMessage(string $method): Phrase
    {
        if ($method === Satispay::METHOD_CODE) {
            return __(
                'To pay with Satispay, your billing address needs to be in one of the following countries: ' .
                'Austria, Belgium, Cyprus, Germany, Estonia, Spain, Finland, France, Greece, Croatia, Hungary, ' .
                'Ireland, Italy, Lithuania, Luxembourg, Latvia, Malta, Netherlands, Portugal, Slovenia, Slovakia.'
            );
        }
        if ($method === Sofort::METHOD_CODE) {
            return __(
                'To pay with SOFORT, your billing address needs to be in one of the following countries: ' .
                'Austria, Belgium, Germany, Spain, Italy, Netherlands.'
            );
        }
        if ($method === Giropay::METHOD_CODE) {
            return __(
                'To pay with Giropay, your billing address needs to be in Germany.'
            );
        }
        if ($method === Ideal::METHOD_CODE) {
            return __(
                'To pay with iDEAL, your billing address needs to be in the Netherlands.'
            );
        }
        if ($method === Mybank::METHOD_CODE) {
            return __(
                'To pay with MyBank, your billing address needs to be in Italy.'
            );
        }

        throw new \Exception('Invalid PPRO method.');
    }
}
