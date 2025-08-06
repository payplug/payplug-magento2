<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\Apm;

use Exception;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
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
        private readonly CountryCollectionFactory $countryCollectionFactory,
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
            } elseif ($this->configHelper->isShippingApmFilteringMode() === false) {
                $params = $this->getRequest()->getParams();
                $allowedCountryIds = $this->allowedCountriesPerPaymentMethod->execute($params['paymentMethod']);

                if (!in_array($params['billingCountry'], $allowedCountryIds)) {
                    $countryCollection = $this->countryCollectionFactory->create();
                    $countryCollection->addFieldToFilter('country_id', ['in' => $allowedCountryIds]);
                    $countryCollection->loadData();

                    $countryNames = [];

                    foreach ($countryCollection as $country) {
                        $countryNames[] = $country->getName();
                    }

                    if (count($countryNames) === 1) {
                        $message = __(
                            'Billing address is not eligible with this payment method. Allowed country : %1',
                            implode(',', $countryNames)
                        );
                    } else {
                        $message = __(
                            'Billing address is not eligible with this payment method. Allowed countries : %1',
                            implode(',', $countryNames)
                        );
                    }


                    $result->setData([
                        'success' => false,
                        'data' => [
                            'message' => $message
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
