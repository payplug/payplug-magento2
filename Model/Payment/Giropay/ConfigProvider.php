<?php

namespace Payplug\Payments\Model\Payment\Giropay;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Payplug\Payments\Gateway\Config\Giropay;
use Payplug\Payments\Model\Payment\PayplugConfigProvider;

class ConfigProvider extends PayplugConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string
     */
    private $methodCode = Giropay::METHOD_CODE;

    /**
     * @var MethodInterface
     */
    private $method;

    /**
     * @param Repository       $assetRepo
     * @param RequestInterface $request
     * @param PaymentHelper    $paymentHelper
     */
    public function __construct(
        Repository $assetRepo,
        RequestInterface $request,
        PaymentHelper $paymentHelper
    ) {
        parent::__construct($assetRepo, $request);
        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
    }

    /**
     * Get Standard payment config
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->method->isAvailable() ? [
            'payment' => [
                $this->methodCode => [
                    'logo' => $this->getViewFileUrl('Payplug_Payments::images/giropay.svg'),
                ],
            ],
        ] : [];
    }
}
