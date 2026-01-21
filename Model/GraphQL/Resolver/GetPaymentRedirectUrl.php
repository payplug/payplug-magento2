<?php

namespace Payplug\Payments\Model\GraphQL\Resolver;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Payplug\Payments\Helper\Data as PayplugDataHelper;

class GetPaymentRedirectUrl implements ResolverInterface
{
    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param PayplugDataHelper $payplugDataHelper
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly PayplugDataHelper $payplugDataHelper
    ) {
    }

    /**
     * Return redirect url for payment
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return string|null
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): ?string {
        $orderId = $value['order_id'] ?? null;

        if ($orderId === null) {
            return null;
        }

        $searcCriteria = $this->searchCriteriaBuilder->addFilter(OrderInterface::INCREMENT_ID, $orderId)->create();
        $orders = $this->orderRepository->getList($searcCriteria)->getItems();
        $order = array_shift($orders);

        if ($order === null) {
            return null;
        }

        $orderPayment = $order->getPayment();

        if ($orderPayment === null) {
            return null;
        }

        $paymentMethodCode = $orderPayment->getMethod();
        $isPayplugPayment = $this->payplugDataHelper->isCodePayplugPayment($paymentMethodCode);

        if ($isPayplugPayment === false) {
            return null;
        }

        $additionalInformation = $orderPayment->getAdditionalInformation();
        $paymentUrl = $additionalInformation['payment_url'] ?? null;

        if ($paymentUrl === null) {
            return null;
        }

        return $paymentUrl;
    }
}
