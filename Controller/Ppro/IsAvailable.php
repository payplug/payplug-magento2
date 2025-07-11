<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\Ppro;

use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Payplug\Payments\Helper\Config;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Service\GetAllowedCountriesPerPaymentMethod;

class IsAvailable extends Action
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly Config $configHelper,
        private readonly GetAllowedCountriesPerPaymentMethod $allowedCountriesPerPaymentMethod,
        private readonly Logger $logger
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
                $allowedCountryIds = $this->allowedCountriesPerPaymentMethod->execute($params['paymentMethod']);
                $selectedCountryIds = [
                    $params['billingCountry'],
                    $params['shippingCountry']
                ];

                if ($allowedCountryIds && !array_intersect($allowedCountryIds, $selectedCountryIds)) {
                    $result->setData([
                        'success' => false,
                        'data' => [
                            'message' => __('Billing or shipping address is not eligible with this payment method.')
                        ],
                    ]);
                }
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());

            $result->setData([
                'success' => false,
                'data' => ['message' => __('An error occurred. Please try again.')]
            ]);
        }

        return $result;
    }
}
