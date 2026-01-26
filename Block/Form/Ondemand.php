<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Block\Form;

use Magento\Framework\View\Element\Template;
use Payplug\Payments\Helper\OndemandOptions;

class Ondemand extends \Magento\Payment\Block\Form
{
    /**
     * @var string
     */
    protected $_template = 'Payplug_Payments::form/ondemand.phtml';

    /**
     * @var OndemandOptions
     */
    private $onDemandHelper;

    /**
     * @param Template\Context $context
     * @param OndemandOptions  $onDemandHelper
     * @param array            $data
     */
    public function __construct(
        Template\Context $context,
        OndemandOptions $onDemandHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->onDemandHelper = $onDemandHelper;
    }

    /**
     * Get available SentBy options
     *
     * @return array
     */
    public function getSentByOptions()
    {
        return $this->onDemandHelper->getAvailableOndemandSentBy();
    }

    /**
     * Get available language options
     *
     * @return array
     */
    public function getLanguages()
    {
        return $this->onDemandHelper->getAvailableOndemandLanguage();
    }

    /**
     * Retrieve field value data from payment additional info object
     *
     * @param   string $field
     *
     * @return  mixed
     */
    public function getAdditionalData($field)
    {
        return $this->escapeHtml($this->getMethod()->getInfoInstance()->getAdditionalInformation($field));
    }
}
