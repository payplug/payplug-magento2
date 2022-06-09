<?php

namespace Payplug\Payments\Model\Payment\Bancontact;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Payplug\Payments\Gateway\Config\Bancontact;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string
     */
    private $methodCode = Bancontact::METHOD_CODE;

    /**
     * @var MethodInterface
     */
    private $method;

    /**
     * @var Repository
     */
    private $assetRepo;

    /**
     * @var RequestInterface
     */
    private $request;

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
        $this->assetRepo = $assetRepo;
        $this->request = $request;
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
                    'logo' => $this->getViewFileUrl('Payplug_Payments::images/bancontact.svg'),
                ],
            ],
        ] : [];
    }

    /**
     * Retrieve url of a view file
     *
     * @param string $fileId
     * @param array  $params
     *
     * @return string
     */
    private function getViewFileUrl($fileId, array $params = [])
    {
        try {
            $params = array_merge(['_secure' => $this->request->isSecure()], $params);

            return $this->assetRepo->getUrlWithParams($fileId, $params);
        } catch (LocalizedException $e) {
            return null;
        }
    }
}
