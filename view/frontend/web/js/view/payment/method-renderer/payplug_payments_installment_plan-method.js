/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

define([
    'Magento_Checkout/js/view/payment/default',
    'Payplug_Payments/js/action/redirect-on-success',
    'Payplug_Payments/js/action/lightbox-on-success',
    'Magento_Checkout/js/model/full-screen-loader',
    'ko'
], function (Component, redirectOnSuccessAction, lightboxOnSuccessAction, fullScreenLoader, ko) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Payplug_Payments/payment/payplug_payments_installment_plan'
        },
        redirectAfterPlaceOrder: false,

        /**
         * Init observable variables
         * @return {Object}
         */
        initObservable: function () {
            this._super();

            this.isActive = ko.computed(function () {
                return this.getCode() === this.isChecked() && '_active';
            }, this);

            return this;
        },

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
