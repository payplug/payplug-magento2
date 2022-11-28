define([
    'Magento_Checkout/js/view/payment/default',
    'Payplug_Payments/js/action/redirect-on-success',
    'Magento_Checkout/js/model/full-screen-loader',
    'ko'
], function (Component, redirectOnSuccessAction, fullScreenLoader, ko) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Payplug_Payments/payment/payplug_payments_amex'
        },

        redirectAfterPlaceOrder: false,
        isLoading: false,
        isAmexPlaceOrderDisabled: ko.observable(false),
        amexDisabledMessage: ko.observable(''),

        afterPlaceOrder: function () {
            fullScreenLoader.stopLoader();
            redirectOnSuccessAction.execute();
        },
        getCardLogo: function() {
            return window.checkoutConfig.payment.payplug_payments_amex.logo;
        },
    });
});
