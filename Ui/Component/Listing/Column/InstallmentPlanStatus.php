<?php

namespace Payplug\Payments\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Payment\Helper\Data;
use Payplug\Payments\Helper\Data as PayplugHelper;

class InstallmentPlanStatus extends Column
{
    /**
     * @var Data
     */
    private $paymentHelper;

    /**
     * @var PayplugHelper
     */
    private $payplugHelper;

    /**
     * Constructor
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param Data $paymentHelper
     * @param PayplugHelper $payplugHelper
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        Data $paymentHelper,
        PayplugHelper $payplugHelper,
        array $components = [],
        array $data = []
    ) {
        $this->paymentHelper = $paymentHelper;
        $this->payplugHelper = $payplugHelper;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $label = 'N/A';
                try {
                    $paymentMethod = $this->paymentHelper
                        ->getMethodInstance($item['payment_method']);

                    if ($paymentMethod->getCode() == \Payplug\Payments\Gateway\Config\InstallmentPlan::METHOD_CODE) {
                        $labels = $this->payplugHelper->getInstallmentPlanStatusesLabel();
                        if (isset($labels[$item[$this->getData('name')]])) {
                            $label = $labels[$item[$this->getData('name')]];
                        }
                    }
                } catch (\Exception $exception) {
                    // Exception is thrown if payment method is not available in system
                    // In this context (order grid), no need to handle the exception
                } finally {
                    $item[$this->getData('name')] = __($label);
                }
            }
        }

        return $dataSource;
    }
}
