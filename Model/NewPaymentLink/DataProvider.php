<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Model\NewPaymentLink;

use Magento\Backend\Model\Session;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Payplug\Payments\Helper\Data;
use Payplug\Payments\Model\ResourceModel\Order\Payment\Collection;

class DataProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var Data
     */
    private $payplugHelper;

    /**
     * @var Session
     */
    private $session;

    /**
     * @param string     $name
     * @param string     $primaryFieldName
     * @param string     $requestFieldName
     * @param Registry   $registry
     * @param Data       $payplugHelper
     * @param Collection $collection
     * @param Session    $session
     * @param array      $meta
     * @param array      $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        Registry $registry,
        Data $payplugHelper,
        Collection $collection,
        Session $session,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);

        $this->registry = $registry;
        $this->payplugHelper = $payplugHelper;
        $this->collection = $collection;
        $this->session = $session;
    }

    /**
     * @inheritdoc
     */
    public function getData()
    {
        /** @var Order $order */
        $order = $this->registry->registry('current_order');
        $orderPayment = $this->payplugHelper->getOrderLastPayment($order->getIncrementId());

        $data = [];
        $data['order_id'] = $order->getId();
        $data['sent_by'] = $orderPayment->getSentBy();
        $data['sent_by_value'] = $orderPayment->getSentByValue();
        $data['language'] = $orderPayment->getLanguage();
        $data['description'] = $orderPayment->getDescription();

        $sessionData = $this->session->getPaymentLinkFormData();
        if (!empty($sessionData)) {
            $data = $sessionData;
            $this->session->unsPaymentLinkFormData();
        }

        $data = [$order->getId() => ['form' => $data]];

        return $data;
    }
}
