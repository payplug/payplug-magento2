<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\ApplePay;

use Magento\Checkout\Controller\Cart\Add as AddToCartController;
use Magento\Checkout\Model\SessionFactory as CheckoutSessionFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;

class CreateApplePayQuote implements HttpPostActionInterface
{
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly CheckoutSessionFactory $checkoutSessionFactory,
        private readonly AddToCartController $addToCartController,
        private readonly CartRepositoryInterface $cartRepository
    ) {
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function execute(): Json
    {
        $checkoutSession = $this->checkoutSessionFactory->create();
        $currentQuote = $checkoutSession->getQuote();
        $currentQuote->removeAllItems();

        $jsonResult = $this->resultJsonFactory->create();
        $addToCartResponse = $this->addToCartController->execute();

        if ($addToCartResponse instanceof HttpResponse) {
            $checkoutSession = $this->checkoutSessionFactory->create();
            $quote = $checkoutSession->getQuote();

            if ($quote->isVirtual() === false) {
                $shippingAddress = $quote->getShippingAddress();
                $shippingAddress->setShippingMethod('');
                $shippingAddress->setCollectShippingRates(true)->collectShippingRates();

                $quote->setTotalsCollectedFlag(false);
                $quote->collectTotals();
                $this->cartRepository->save($quote);
            }

            return $jsonResult->setData([
                'success' => true,
                'message' => 'Quote created',
                'base_amount' => (float)$quote->getGrandTotal(),
                'is_virtual' => $quote->isVirtual()
            ]);
        } else {
            return $jsonResult->setData(['error' => true, 'message' => __('An error occurred while adding product.')]);
        }
    }
}
