/* @api */
define([
    'Payplug_Payments/js/view/payment/method-renderer/ppro-payment-renderer',
    'jquery',
    'mage/translate'
], function (Component, $) {
    'use strict';

    return Component.extend({
        getConfiguration: function() {
            return window.checkoutConfig.payment.payplug_payments_mybank;
        },
    });
});
