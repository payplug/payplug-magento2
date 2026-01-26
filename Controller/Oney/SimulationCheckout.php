<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Controller\Oney;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObject;
use Payplug\Payments\Helper\Oney;
use Payplug\Payments\Logger\Logger;

class SimulationCheckout extends Action
{
    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param Oney $oneyHelper
     * @param Logger $logger
     */
    public function __construct(
        Context $context,
        private JsonFactory $resultJsonFactory,
        private Oney $oneyHelper,
        private Logger $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        try {
            $params = $this->getRequest()->getParams();
            $isVirtual = (bool) ($params['isVirtual'] ?? false);
            $shippingCountry = $params['shippingCountry'] ?? null;
            if ($isVirtual) {
                $shippingCountry = null;
            }

            $simulationResult = $this->oneyHelper->getOneySimulationCheckout(
                (float)$params['amount'],
                $params['billingCountry'] ?? null,
                $shippingCountry,
                $params['paymentMethod'] ?? null
            );

            $result->setData([
                'success' => true,
                'data' => $this->transformSimulationResultToArray($simulationResult),
            ]);
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'An error occurred while getting oney simulation in checkout : %s',
                $e->getMessage()
            ));
            $result->setData([
                'success' => false,
                'data' => ['message' => __('An error occurred while getting Oney details. Please try again.')]
            ]);
        }

        return $result;
    }

    /**
     * Transform simulation object into array
     *
     * @param DataObject $object
     * @return array
     */
    private function transformSimulationResultToArray(DataObject $object): array
    {
        $currentData = $object->toArray();
        foreach ($currentData as $currentKey => $currentDatum) {
            if ($currentDatum instanceof DataObject) {
                $currentData[$currentKey] = $this->transformSimulationResultToArray($currentDatum);
            } elseif (is_array($currentDatum)) {
                foreach ($currentDatum as $key => $value) {
                    if ($value instanceof DataObject) {
                        $currentData[$currentKey][$key] = $this->transformSimulationResultToArray($value);
                    }
                }
            }
        }

        return $currentData;
    }
}
