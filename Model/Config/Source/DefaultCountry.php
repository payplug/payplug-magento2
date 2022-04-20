<?php

namespace Payplug\Payments\Model\Config\Source;

use Magento\Directory\Model\ResourceModel\Country\Collection as CountryCollection;
use Magento\Framework\Option\ArrayInterface;
use Payplug\Payments\Helper\Country;

class DefaultCountry implements ArrayInterface
{
    /**
     * @var CountryCollection
     */
    private $countryCollection;

    /**
     * @var Country
     */
    private $countryHelper;

    /**
     * @param CountryCollection $countryCollection
     * @param Country           $countryHelper
     */
    public function __construct(CountryCollection $countryCollection, Country $countryHelper)
    {
        $this->countryCollection = $countryCollection;
        $this->countryHelper = $countryHelper;
    }

    /**
     * Get allowed country list
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = $this->countryCollection->loadData()->toOptionArray(false);

        $allowedCountries = $this->countryHelper->getAllowedCountries();
        foreach ($options as $key => $countryData) {
            if (!in_array($countryData['value'], $allowedCountries)) {
                unset($options[$key]);
            }
        }

        return $options;
    }
}
