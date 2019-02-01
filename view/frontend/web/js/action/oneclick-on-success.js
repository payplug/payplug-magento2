define(
    [
        'mage/url',
        'jquery'
    ],
    function (url, jQuery) {
        'use strict';

        return {
            redirectUrl: 'payplug_payments/payment/oneClick',

            /**
             * Provide redirect to page
             */
            execute: function (customerCardId) {
                var newForm = jQuery('<form>', {
                    'action': url.build(this.redirectUrl),
                    'method': 'POST'
                }).append(jQuery('<input>', {
                    'name': 'customer_card_id',
                    'value': customerCardId,
                    'type': 'hidden'
                }));
                jQuery('body').append(newForm);
                newForm.submit();
            }
        };
    }
);
