<?php

namespace Payplug\Payments\Model\Customer;

class Card extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{
    public const CACHE_TAG = 'payplug_payments_card';

    public const CUSTOMER_ID = 'customer_id';

    public const CUSTOMER_CARD_ID = 'customer_card_id';

    public const COMPANY_ID = 'company_id';

    public const IS_SANDBOX = 'is_sandbox';

    public const CARD_TOKEN = 'card_token';

    public const LAST_FOUR = 'last4';

    public const EXP_DATE = 'exp_date';

    public const BRAND = 'brand';

    public const COUNTRY = 'country';

    public const METADATA = 'metadata';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(\Payplug\Payments\Model\ResourceModel\Customer\Card::class);
    }

    /**
     * Get entity identities
     *
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * Get customer id
     *
     * @return int
     */
    public function getCustomerId()
    {
        return $this->_getData(self::CUSTOMER_ID);
    }

    /**
     * Set customer id
     *
     * @param int $customerId
     *
     * @return $this
     */
    public function setCustomerId($customerId)
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    /**
     * Get customer card id
     *
     * @return int
     */
    public function getCustomerCardId()
    {
        return $this->_getData(self::CUSTOMER_CARD_ID);
    }

    /**
     * Set customer card id
     *
     * @param int $customerCardId
     *
     * @return $this
     */
    public function setCustomerCardId($customerCardId)
    {
        return $this->setData(self::CUSTOMER_CARD_ID, $customerCardId);
    }

    /**
     * Get company id
     *
     * @return int
     */
    public function getCompanyId()
    {
        return $this->_getData(self::COMPANY_ID);
    }

    /**
     * Set company id
     *
     * @param int $companyId
     *
     * @return $this
     */
    public function setCompanyId($companyId)
    {
        return $this->setData(self::COMPANY_ID, $companyId);
    }

    /**
     * Get is sandbox
     *
     * @return bool
     */
    public function isSandbox()
    {
        return (bool) $this->_getData(self::IS_SANDBOX);
    }

    /**
     * Set is sandbox
     *
     * @param bool $isSandbox
     *
     * @return $this
     */
    public function setIsSandbox($isSandbox)
    {
        return $this->setData(self::IS_SANDBOX, $isSandbox);
    }

    /**
     * Get card token
     *
     * @return string
     */
    public function getCardToken()
    {
        return $this->_getData(self::CARD_TOKEN);
    }

    /**
     * Set card token
     *
     * @param string $cardToken
     *
     * @return $this
     */
    public function setCardToken($cardToken)
    {
        return $this->setData(self::CARD_TOKEN, $cardToken);
    }

    /**
     * Get last four chars
     *
     * @return string
     */
    public function getLastFour()
    {
        return $this->_getData(self::LAST_FOUR);
    }

    /**
     * Set last four chars
     *
     * @param string $lastFour
     *
     * @return $this
     */
    public function setLastFour($lastFour)
    {
        return $this->setData(self::LAST_FOUR, $lastFour);
    }

    /**
     * Get expiration date
     *
     * @return string
     */
    public function getExpDate()
    {
        return $this->_getData(self::EXP_DATE);
    }

    /**
     * Set expiration date
     *
     * @param string $expDate
     *
     * @return $this
     */
    public function setExpDate($expDate)
    {
        return $this->setData(self::EXP_DATE, $expDate);
    }

    /**
     * Get brand
     *
     * @return string
     */
    public function getBrand()
    {
        return $this->_getData(self::BRAND);
    }

    /**
     * Set brand
     *
     * @param string $brand
     *
     * @return $this
     */
    public function setBrand($brand)
    {
        return $this->setData(self::BRAND, $brand);
    }

    /**
     * Get country
     *
     * @return string
     */
    public function getCountry()
    {
        return $this->_getData(self::COUNTRY);
    }

    /**
     * Set country
     *
     * @param string $country
     *
     * @return $this
     */
    public function setCountry($country)
    {
        return $this->setData(self::COUNTRY, $country);
    }

    /**
     * Get metadata
     *
     * @return string
     */
    public function getMetadata()
    {
        return $this->_getData(self::METADATA);
    }

    /**
     * Set metadata
     *
     * @param string $metadata
     *
     * @return $this
     */
    public function setMetadata($metadata)
    {
        return $this->setData(self::METADATA, $metadata);
    }
}
