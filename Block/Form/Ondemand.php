<?php

namespace Payplug\Payments\Block\Form;

use Payplug\Payments\Model\Order\Payment;

class Ondemand extends \Magento\Payment\Block\Form
{
    protected $_template = 'Payplug_Payments::form/ondemand.phtml';

    /**
     * @return array
     */
    public function getSentByOptions()
    {
        return Payment::getAvailableOndemandSentBy();
    }

    /**
     * @return array
     */
    public function getLanguages()
    {
        return Payment::getAvailableOndemandLanguage();
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
