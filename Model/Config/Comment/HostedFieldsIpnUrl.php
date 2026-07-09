<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Payplug\Payments\Model\Config\Comment;

use Magento\Config\Model\Config\CommentInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class HostedFieldsIpnUrl implements CommentInterface
{
    private const IPN_PATH = 'payplug_payments/payment/ipn';
    private const IPN_WEBSITE_PARAM = 'website_id';

    /**
     * @param StoreManagerInterface $storeManager
     * @param RequestInterface $request
     * @param Escaper $escaper
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly RequestInterface $request,
        private readonly Escaper $escaper
    ) {
    }

    /**
     * Build the IPN URL comment for the website being configured.
     *
     * @param string $elementValue
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getCommentText($elementValue): string
    {
        $intro = (string) __('Configure the following IPN URL in your Dalenys Merchant Console.');

        try {
            $url = $this->buildWebsiteIpnUrl($this->resolveWebsiteId());
        } catch (LocalizedException) {
            return $this->escaper->escapeHtml($intro);
        }

        if ($url === null) {
            return $this->escaper->escapeHtml($intro);
        }

        return sprintf(
            '%s<br/><code class="hosted-fields-ipn-url">%s</code>',
            $this->escaper->escapeHtml($intro),
            $this->escaper->escapeHtml($url)
        );
    }

    /**
     * Resolve the website ID to display based on the current admin configuration scope.
     *
     * @return int
     * @throws LocalizedException
     */
    private function resolveWebsiteId(): int
    {
        $websiteId = (int) $this->request->getParam('website');

        if ($websiteId > 0) {
            return $websiteId;
        }

        $defaultStore = $this->storeManager->getDefaultStoreView();

        if ($defaultStore === null) {
            throw new LocalizedException(__('Unable to resolve the default store view.'));
        }

        return (int) $defaultStore->getWebsiteId();
    }

    /**
     * Build the website IPN URL using the website's default store base URL without store code suffix.
     *
     * @param int $websiteId
     * @return string|null
     * @throws LocalizedException
     */
    private function buildWebsiteIpnUrl(int $websiteId): ?string
    {
        $defaultStore = $this->storeManager->getWebsite($websiteId)->getDefaultStore();

        if ($defaultStore === null) {
            return null;
        }

        $baseUrl = rtrim($defaultStore->getBaseUrl(UrlInterface::URL_TYPE_DIRECT_LINK, true), '/');

        return sprintf('%s/%s/%s/%d', $baseUrl, self::IPN_PATH, self::IPN_WEBSITE_PARAM, $websiteId);
    }
}
