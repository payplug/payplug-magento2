<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\Amex;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
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
                    'data' => ['message' => __('The payment is not available for the TEST mode.')]
                ]);
            }
        } catch (\Exception $e) {
            $result->setData([
                'success' => false,
                'data' => ['message' => __('An error occurred while getting Amex details. Please try again.')]
            ]);
        }

        return $result;
    }
}
