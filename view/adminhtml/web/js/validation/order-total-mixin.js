/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

define([
    'jquery'
], function ($) {
    'use strict';

    return function (validation) {
        $.validator.addMethod(
            'validate-payplug-order-total',
            function (value, element, params) {
                var amount = parseFloat(value);

                return value === element.defaultValue
                    || isNaN(amount)
                    || amount === 0
                    || (amount >= params.min && amount <= params.max);
            },
            function (params) {
                return params.message;
            }
        );

        return validation;
    };
});
