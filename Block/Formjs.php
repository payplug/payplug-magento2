<?php

namespace Payplug\Payments\Block;

use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;
use Payplug\Payments\Helper\Config;

class Formjs extends \Magento\Framework\View\Element\Template
{
    /**
     * @var Config
     */
    private $helper;

    /**
     * @param Template\Context $context
     * @param Config           $helper
     * @param array            $data
     */
    public function __construct(Template\Context $context, Config $helper, array $data = [])
    {
        parent::__construct($context, $data);
        $this->helper = $helper;
    }

    /**
     * Get PayPlug js url
     *
     * @return string
     */
    public function getJsUrl()
    {
        $url = $this->getRequest()->getServer('PAYPLUG_API_URL', 'https://api.payplug.com');
        $url .= '/js/1/form.latest.js';

        return $url;
    }

    /**
     * Get embedded option
     *
     * @return bool
     */
    public function isEmbedded()
    {
        return $this->helper->isEmbedded() || $this->helper->isOneClick();
    }
}
