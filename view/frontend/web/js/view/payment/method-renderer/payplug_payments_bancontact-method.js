/* @api */
define([
    'Magento_Checkout/js/view/payment/default',
    'Payplug_Payments/js/action/redirect-on-success',
    'Magento_Checkout/js/model/full-screen-loader'
], function (Component, redirectOnSuccessAction, fullScreenLoader) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Payplug_Payments/payment/payplug_payments_bancontact'
        },

        redirectAfterPlaceOrder: false,

        afterPlaceOrder: function () {
            fullScreenLoader.stopLoader();
            redirectOnSuccessAction.execute();
        },
        getCardLogo: function() {
            return window.checkoutConfig.payment.payplug_payments_bancontact.logo;
        }
    });
});
