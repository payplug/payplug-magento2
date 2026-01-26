<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Model\Payment;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\Repository;

abstract class PayplugConfigProvider
{
    /**
     * @param Repository $assetRepo
     * @param RequestInterface $request
     */
    public function __construct(
        protected Repository $assetRepo,
        protected RequestInterface $request
    ) {
    }

    /**
     * Get the URL of a view file
     *
     * @param string $fileId
     * @param array $params
     * @return string|null
     */
    protected function getViewFileUrl(string $fileId, array $params = []): ?string
    {
        try {
            $params = array_merge(['_secure' => $this->request->isSecure()], $params);

            return $this->assetRepo->getUrlWithParams($fileId, $params);
        } catch (LocalizedException) {
            return null;
        }
    }
}
