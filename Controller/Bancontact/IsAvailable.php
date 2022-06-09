<?php

namespace Payplug\Payments\Controller\Bancontact;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
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
            }
        } catch (\Exception $e) {
            $result->setData([
                'success' => false,
                'data' => ['message' => __('An error occurred while getting Bancontact details. Please try again.')]
            ]);
        }

        return $result;
    }
}
