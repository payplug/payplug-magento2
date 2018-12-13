/* @api */
define([
    'Magento_Checkout/js/view/payment/default',
    'Payplug_Payments/js/action/redirect-on-success',
    'Payplug_Payments/js/action/lightbox-on-success'
], function (Component, redirectOnSuccessAction, lightboxOnSuccessAction) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Payplug_Payments/payment/payplug_payments'
        },

        redirectAfterPlaceOrder: false,

        afterPlaceOrder: function () {
            if (window.checkoutConfig.payment.payplug_payments.is_embedded) {
                lightboxOnSuccessAction.execute();
                return;
            }
            redirectOnSuccessAction.execute();
        },
        getCardLogo: function() {
            return window.checkoutConfig.payment.payplug_payments.logo;
        }
    });
});
