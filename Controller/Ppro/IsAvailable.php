<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\Ppro;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Phrase;
use Payplug\Payments\Gateway\Config\Ideal;
use Payplug\Payments\Gateway\Config\Mybank;
use Payplug\Payments\Gateway\Config\Satispay;
use Payplug\Payments\Helper\Config;

class IsAvailable extends Action
{
    public function __construct(
        Context $context,
        private JsonFactory $resultJsonFactory,
        private Config $configHelper
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();
        $result->setData([
            'success' => true,
        ]);

        try {
            if ($this->configHelper->getIsSandbox()) {
                $result->setData([
                    'success' => false,
                    'data' => [
                        'message' => __('The payment is not available for the TEST mode.'),
                        'type' => 'mode-test',
                    ],
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
     * Retrieve error message for given payment method
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
