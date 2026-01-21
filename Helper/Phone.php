<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Helper;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use Magento\Framework\App\Helper\AbstractHelper;

class Phone extends AbstractHelper
{
    /**
     * Get formatted phone
     *
     * @param string $phone
     * @param string $country
     * @param bool $checkRegion
     * @return array|null
     */
    public function getPhoneInfo(string $phone, string $country, bool $checkRegion = false): ?array
    {
        try {
            $phoneNumberUtil = PhoneNumberUtil::getInstance();
            $phoneNumber = $phoneNumberUtil->parse($phone, $country);

            if (($checkRegion === true && $phoneNumberUtil->isValidNumberForRegion($phoneNumber, $country) === false)
                || $phoneNumberUtil->isValidNumber($phoneNumber) === false
            ) {
                return null;
            }

            $formattedPhone = $phoneNumberUtil->format($phoneNumber, PhoneNumberFormat::E164);

            $numberType = $phoneNumberUtil->getNumberType($phoneNumber);
            $landline = false;
            $mobile = false;
            switch ($numberType) {
                case PhoneNumberType::FIXED_LINE:
                    $landline = true;
                    break;
                case PhoneNumberType::MOBILE:
                    $mobile = true;
                    break;
                case PhoneNumberType::FIXED_LINE_OR_MOBILE:
                    $landline = true;
                    $mobile = true;
                    break;
                case PhoneNumberType::VOIP:
                    $landline = true;
                    break;
            }

            return [
                'phone' => $formattedPhone,
                'landline' => $landline,
                'mobile' => $mobile,
            ];
        } catch (NumberParseException $e) {
            return null;
        }
    }
}
