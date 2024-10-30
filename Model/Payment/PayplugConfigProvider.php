<?php

declare(strict_types=1);

namespace Payplug\Payments\Model\Payment;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\Repository;
abstract class PayplugConfigProvider
{
    public function __construct(
        protected Repository $assetRepo,
        protected RequestInterface $request
    ) {
    }

    protected function getViewFileUrl(string $fileId, array $params = []): ?string
    {
        try {
            $params = array_merge(['_secure' => $this->request->isSecure()], $params);

            return $this->assetRepo->getUrlWithParams($fileId, $params);
        } catch (LocalizedException $e) {
            return null;
        }
    }
}
