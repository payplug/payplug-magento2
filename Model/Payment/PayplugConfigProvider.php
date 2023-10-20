<?php

namespace Payplug\Payments\Model\Payment;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\Repository;

abstract class PayplugConfigProvider
{
    /**
     * @var Repository
     */
    protected $assetRepo;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @param Repository       $assetRepo
     * @param RequestInterface $request
     */
    public function __construct(
        Repository $assetRepo,
        RequestInterface $request
    ) {
        $this->assetRepo = $assetRepo;
        $this->request = $request;
    }

    /**
     * Retrieve url of a view file
     *
     * @param string $fileId
     * @param array  $params
     *
     * @return string
     */
    protected function getViewFileUrl($fileId, array $params = [])
    {
        try {
            $params = array_merge(['_secure' => $this->request->isSecure()], $params);

            return $this->assetRepo->getUrlWithParams($fileId, $params);
        } catch (LocalizedException $e) {
            return null;
        }
    }
}
