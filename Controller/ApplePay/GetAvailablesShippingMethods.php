<?php

declare(strict_types=1);

namespace Payplug\Payments\Controller\ApplePay;

use Exception;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote\AddressFactory;
use Payplug\Payments\Logger\Logger;
use Payplug\Payments\Service\GetQuoteApplePayAvailableMethods;

class GetAvailablesShippingMethods implements HttpGetActionInterface
{
    public function __construct(
        private RequestInterface $request,
        private JsonFactory $resultJsonFactory,
        private Logger $logger,
        private Validator $formKeyValidator,
        private AddressFactory $addressFactory,
        private CheckoutSession $checkoutSession,
        private CartRepositoryInterface $cartRepository,
        private GetQuoteApplePayAvailableMethods $getCurrentQuoteAvailableMethods
    ) {}

    /**
     * Give Available shipping methods datas for Given ApplePayPaymentContact address data
     *
     */
    public function execute(): Json
    {
        /** @var Json $response */
        $response = $this->resultJsonFactory->create();
        $response->setData([
            'error' => true,
            'message' => (string)__('An error occurred while retrieving available shipping methods'),
        ]);

        $formKeyValidation = $this->formKeyValidator->validate($this->request);
        if (!$formKeyValidation) {
            return $response;
        }

        try {
            $applePayData = $this->request->getParams();
            $firstname = !empty($applePayData['givenName']) ? $applePayData['givenName'] : 'PlaceHolder first name';
            $lastname = !empty($applePayData['familyName']) ? $applePayData['familyName'] : 'PlaceHolder last name';
            $city = !empty($applePayData['locality']) ? $applePayData['locality'] : '';
            $postcode = !empty($applePayData['postalCode']) ? $applePayData['postalCode'] : '';
            $countryId = !empty($applePayData['countryCode']) ? $applePayData['countryCode'] : '';

            $address = $this->addressFactory->create();
            $address->setFirstname($firstname)
                ->setLastname($lastname)
                ->setCity($city)
                ->setPostcode($postcode)
                ->setCountryId($countryId);

            $quote = $this->checkoutSession->getQuote()->setShippingAddress($address);
            $this->cartRepository->save($quote);

            $response->setData([
                    'error' => false,
                    'methods' => $this->getCurrentQuoteAvailableMethods->execute((int)$quote->getId())
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('An error occurred while retrieving available shipping methods', [
                'message' => $e->getMessage(),
                'exception' => $e,
                'datas' => $applePayData
            ]);
        }
        return $response;
    }
}
