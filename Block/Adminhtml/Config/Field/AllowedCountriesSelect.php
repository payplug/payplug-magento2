<?php

namespace Payplug\Payments\Block\Adminhtml\Config\Field;

use Magento\Directory\Model\CountryFactory;
use Magento\Framework\Data\Form\Element\CollectionFactory;
use Magento\Framework\Data\Form\Element\Factory;
use Magento\Framework\Data\Form\Element\Select;
use Magento\Framework\Escaper;
use Magento\Framework\Math\Random;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Payplug\Payments\Service\GetAllowedCountriesPerPaymentMethod;

class AllowedCountriesSelect extends Select
{
    /**
     * @param GetAllowedCountriesPerPaymentMethod $getAllowedCountriesPerPaymentMethod
     * @param CountryFactory $countryFactory
     * @param Factory $factoryElement
     * @param CollectionFactory $factoryCollection
     * @param Escaper $escaper
     * @param array $data
     * @param SecureHtmlRenderer|null $secureRenderer
     * @param Random|null $random
     */
    public function __construct(
        private readonly GetAllowedCountriesPerPaymentMethod $getAllowedCountriesPerPaymentMethod,
        private readonly CountryFactory $countryFactory,
        Factory $factoryElement,
        CollectionFactory $factoryCollection,
        Escaper $escaper,
        $data = [],
        ?SecureHtmlRenderer $secureRenderer = null,
        ?Random $random = null
    ) {
        $data['disabled'] = true;

        parent::__construct($factoryElement, $factoryCollection, $escaper, $data, $secureRenderer, $random);
    }

    /**
     * Get comment for the field
     *
     * @return string
     */
    public function getComment(): string
    {
        $name = $this->getData('name');

        if (!preg_match('/groups\[([^]]+)]\[/', $name, $matches)) {
            return parent::getData('comment');
        }

        $paymentMethod = $matches[1];
        $allowedCountries = $this->getAllowedCountriesPerPaymentMethod->execute($paymentMethod);

        foreach ($allowedCountries as &$allowedCountry) {
            $countryModel = $this->countryFactory->create()->loadByCode($allowedCountry);
            $allowedCountry = $countryModel->getName();
        }

        if ($allowedCountries) {
            return __(
                'This payment method is only available for payers with billing or shipping addresses in %1.',
                implode(', ', $allowedCountries)
            );
        }

        return __('This payment method is available for payers with billing or shipping addresses in all countries.');
    }
}
