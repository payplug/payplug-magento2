<?php

namespace Payplug\Payments\Model\Customer;

class Card extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{
    const CACHE_TAG = 'payplug_payments_card';

    const CUSTOMER_ID = 'customer_id';

    const CUSTOMER_CARD_ID = 'customer_card_id';

    const COMPANY_ID = 'company_id';

    const IS_SANDBOX = 'is_sandbox';

    const CARD_TOKEN = 'card_token';

    const LAST_FOUR = 'last4';

    const EXP_DATE = 'exp_date';

    const BRAND = 'brand';

    const COUNTRY = 'country';

    const METADATA = 'metadata';

    protected function _construct()
    {
        $this->_init('Payplug\Payments\Model\ResourceModel\Customer\Card');
    }

    /**
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * @return int
     */
    public function getCustomerId()
    {
        return $this->_getData(self::CUSTOMER_ID);
    }

    /**
     * @param int $customerId
     *
     * @return $this
     */
    public function setCustomerId($customerId)
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    /**
     * @return int
     */
    public function getCustomerCardId()
    {
        return $this->_getData(self::CUSTOMER_CARD_ID);
    }

    /**
     * @param int $customerCardId
     *
     * @return $this
     */
    public function setCustomerCardId($customerCardId)
    {
        return $this->setData(self::CUSTOMER_CARD_ID, $customerCardId);
    }

    /**
     * @return int
     */
    public function getCompanyId()
    {
        return $this->_getData(self::COMPANY_ID);
    }

    /**
     * @param int $companyId
     *
     * @return $this
     */
    public function setCompanyId($companyId)
    {
        return $this->setData(self::COMPANY_ID, $companyId);
    }

    /**
     * @return bool
     */
    public function isSandbox()
    {
        return (bool) $this->_getData(self::IS_SANDBOX);
    }

    /**
     * @param bool $isSandbox
     *
     * @return $this
     */
    public function setIsSandbox($isSandbox)
    {
        return $this->setData(self::IS_SANDBOX, $isSandbox);
    }

    /**
     * @return string
     */
    public function getCardToken()
    {
        return $this->_getData(self::CARD_TOKEN);
    }

    /**
     * @param string $cardToken
     *
     * @return $this
     */
    public function setCardToken($cardToken)
    {
        return $this->setData(self::CARD_TOKEN, $cardToken);
    }

    /**
     * @return string
     */
    public function getLastFour()
    {
        return $this->_getData(self::LAST_FOUR);
    }

    /**
     * @param string $lastFour
     *
     * @return $this
     */
    public function setLastFour($lastFour)
    {
        return $this->setData(self::LAST_FOUR, $lastFour);
    }

    /**
     * @return string
     */
    public function getExpDate()
    {
        return $this->_getData(self::EXP_DATE);
    }

    /**
     * @param string $expDate
     *
     * @return $this
     */
    public function setExpDate($expDate)
    {
        return $this->setData(self::EXP_DATE, $expDate);
    }

    /**
     * @return string
     */
    public function getBrand()
    {
        return $this->_getData(self::BRAND);
    }

    /**
     * @param string $brand
     *
     * @return $this
     */
    public function setBrand($brand)
    {
        return $this->setData(self::BRAND, $brand);
    }

    /**
     * @return string
     */
    public function getCountry()
    {
        return $this->_getData(self::COUNTRY);
    }

    /**
     * @param string $country
     *
     * @return $this
     */
    public function setCountry($country)
    {
        return $this->setData(self::COUNTRY, $country);
    }

    /**
     * @return string
     */
    public function getMetadata()
    {
        return $this->_getData(self::METADATA);
    }

    /**
     * @param string $metadata
     *
     * @return $this
     */
    public function setMetadata($metadata)
    {
        return $this->setData(self::METADATA, $metadata);
    }
}
