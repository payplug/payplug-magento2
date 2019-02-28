define(
    [
        'mage/url',
        'jquery'
    ],
    function (url, jQuery) {
        'use strict';

        return {
            redirectUrl: 'payplug_payments/payment/paymentReturn',

            /**
             * Provide redirect to page
             */
            execute: function () {
                window.location.replace(url.build(this.redirectUrl));
            }
        };
    }
);
