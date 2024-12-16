<?php

declare(strict_types=1);

namespace Payplug\Payments\Model\Payment\Amex;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Payplug\Payments\Gateway\Config\Amex;
use Payplug\Payments\Model\Payment\PayplugConfigProvider;

class ConfigProvider extends PayplugConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string
     */
    private $methodCode = Amex::METHOD_CODE;

    /**
     * @var MethodInterface
     */
    private $method;

    public function __construct(
        protected Repository $assetRepo,
        protected RequestInterface $request,
        protected PaymentHelper $paymentHelper
    ) {
        parent::__construct($assetRepo, $request);
        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
    }

    /**
     * Get Standard payment config
     */
    public function getConfig(): array
    {
        return $this->method->isAvailable() ? [
            'payment' => [
                $this->methodCode => [
                    'logo' => $this->getViewFileUrl('Payplug_Payments::images/logos/amex.svg'),
                ],
            ],
        ] : [];
    }
}
