/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

define(
    [
        'mage/url'
    ],
    function (url) {
        'use strict';

        return {
            redirectUrl: 'payplug_payments/payment/standard',

            /**
             * Provide redirect to page
             */
            execute: function () {
                window.location.href = url.build(this.redirectUrl);
            }
        };
    }
);
