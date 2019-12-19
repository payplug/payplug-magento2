<?php

namespace Payplug\Payments\Block\Adminhtml\NewPaymentLink;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

abstract class AbstractButton implements ButtonProviderInterface
{
    /**
     * @var Context
     */
    protected $context;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @param Context  $context
     * @param Registry $registry
     */
    public function __construct(Context $context, Registry $registry)
    {
        $this->context = $context;
        $this->registry = $registry;
    }
}
