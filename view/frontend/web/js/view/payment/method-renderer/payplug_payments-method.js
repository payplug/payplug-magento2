/* @api */
define([
    'Magento_Checkout/js/view/payment/default',
    'Payplug_Payments/js/action/redirect-on-success'
], function (Component, redirectOnSuccessAction) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Payplug_Payments/payment/payplug_payments'
        },

        redirectAfterPlaceOrder: false,

        afterPlaceOrder: function () {
            redirectOnSuccessAction.execute();
        }
    });
});
