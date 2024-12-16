<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\ApplePay;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\OrderFactory;
use Payplug\Exception\PayplugException;
use Payplug\Payments\Controller\Payment\AbstractPayment;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Service\GetCurrentOrderIncrementId;

class GetTransactionData extends AbstractPayment
{
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderFactory $salesOrderFactory,
        Logger $logger,
        Data $payplugHelper,
        protected GetCurrentOrderIncrementId $currentOrderIncrementId
    ) {
        parent::__construct($context, $checkoutSession, $salesOrderFactory, $logger, $payplugHelper);
    }

    /**
     * Retrieve PayPlug Apple Pay transaction data
     */
    public function execute(): Json
    {
        /** @var Json $response */
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $responseParams = [
            'merchand_data' => [],
            'error' => true,
            'message' => (string)__('An error occurred while processing the order.'),
        ];

        try {
            $order = $this->currentOrderIncrementId->getLastRealOrder();
            if (!$order) {
                throw new \Exception('Could not retrieve last order in GetTransactionData');
            }
            $merchandSession = $order->getPayment()->getAdditionalInformation('merchand_session');
            $order->getPayment()->unsAdditionalInformation('merchand_session');

            if (empty($merchandSession)) {
                throw new \Exception('Could not retrieve merchand session');
            }

            $response->setData([
                'merchand_data' => $merchandSession,
                'error' => false,
            ]);

            return $response;
        } catch (PayplugException $e) {
            $this->logger->error('Could not retrieve apple pay transaction data', [
                'message' => $e->__toString(),
                'exception' => $e,
            ]);
            $response->setData($responseParams);

            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Could not retrieve apple pay transaction data', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            $response->setData($responseParams);

            return $response;
        }
    }
}
