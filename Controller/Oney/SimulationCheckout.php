<?php

namespace Payplug\Payments\Controller\Oney;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObject;
use Payplug\Payments\Helper\Oney;
use Payplug\Payments\Logger\Logger;

class SimulationCheckout extends \Magento\Framework\App\Action\Action
{
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var Oney
     */
    private $oneyHelper;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param Context     $context
     * @param JsonFactory $resultJsonFactory
     * @param Oney        $oneyHelper
     * @param Logger      $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Oney $oneyHelper,
        Logger $logger
    ) {
        parent::__construct($context);

        $this->resultJsonFactory = $resultJsonFactory;
        $this->oneyHelper = $oneyHelper;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function execute()
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
                $params['amount'],
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
     * @param DataObject $object
     *
     * @return array
     */
    private function transformSimulationResultToArray(DataObject $object)
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
