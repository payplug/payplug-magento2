<?php

namespace Payplug\Payments\Block;

use Magento\Framework\View\Element\Template;
use Payplug\Payments\Helper\Data;

class Formjs extends \Magento\Framework\View\Element\Template
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @param Template\Context $context
     * @param Data             $helper
     * @param array            $data
     */
    public function __construct(Template\Context $context, Data $helper, array $data = [])
    {
        parent::__construct($context, $data);
        $this->helper = $helper;
    }

    /**
     * @return string
     */
    public function getJsUrl()
    {
        $url = 'https://api.payplug.com';
        if (isset($_SERVER['PAYPLUG_API_URL'])) {
            $url = $_SERVER['PAYPLUG_API_URL'];
        }

        $url .= '/js/1.3/form.js';

        return $url;
    }

    /**
     * @return bool
     */
    public function isEmbedded()
    {
        return $this->helper->isEmbedded();
    }
}
