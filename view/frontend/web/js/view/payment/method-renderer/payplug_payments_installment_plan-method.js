/* @api */
define([
    'Magento_Checkout/js/view/payment/default',
    'Payplug_Payments/js/action/redirect-on-success',
    'Payplug_Payments/js/action/lightbox-on-success',
    'Magento_Checkout/js/model/full-screen-loader'
], function (Component, redirectOnSuccessAction, lightboxOnSuccessAction, fullScreenLoader) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Payplug_Payments/payment/payplug_payments_installment_plan'
        },

        redirectAfterPlaceOrder: false,

        afterPlaceOrder: function () {
            fullScreenLoader.stopLoader();
            if (window.checkoutConfig.payment.payplug_payments_installment_plan.is_embedded) {
                lightboxOnSuccessAction.execute();
                return;
            }
            redirectOnSuccessAction.execute();
        },
        getCardLogo: function() {
            return window.checkoutConfig.payment.payplug_payments_installment_plan.logo;
        }
    });
});
