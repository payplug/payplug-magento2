define([
    'Magento_Checkout/js/view/payment/default',
    'Payplug_Payments/js/action/redirect-on-success',
    'Magento_Checkout/js/model/full-screen-loader',
    'jquery',
    'Magento_Checkout/js/model/quote',
    'mage/url',
    'ko',
    'mage/translate'
], function (Component, redirectOnSuccessAction, fullScreenLoader, $, quote, urlBuilder, ko) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Payplug_Payments/payment/payplug_payments_amex'
        },

        redirectAfterPlaceOrder: false,
        isLoading: false,
        isAmexPlaceOrderDisabled: ko.observable(false),
        amexDisabledMessage: ko.observable(''),

        initialize: function () {
            var self = this;

            this._super();
            quote.paymentMethod.subscribe(function (value) {
                self.isLoading = false;
                if (value && value.method === self.getCode()) {
                    self.updateAmex();
                }
            });
            quote.shippingAddress.subscribe(function () {
                if (self.getCode() === self.isChecked()) {
                    self.updateAmex();
                }
            });
            quote.billingAddress.subscribe(function () {
                if (quote.billingAddress() !== null) {
                    if (self.getCode() === self.isChecked()) {
                        self.updateAmex();
                    }
                }
            });
            quote.totals.subscribe(function () {
                if (self.getCode() === self.isChecked()) {
                    self.updateAmex();
                }
            });
            quote.shippingMethod.subscribe(function () {
                if (self.getCode() === self.isChecked()) {
                    self.updateAmex();
                }
            });

            return this;
        },

        afterPlaceOrder: function () {
            fullScreenLoader.stopLoader();
            redirectOnSuccessAction.execute();
        },
        getCardLogo: function() {
            return window.checkoutConfig.payment.payplug_payments_amex.logo;
        },
        updateAmex: function() {
            var self = this;
            if (self.isLoading) {
                return false;
            }
            self.updateLoading(true);
            try {
                $.ajax({
                    url: urlBuilder.build('payplug_payments/amex/isAvailable'),
                    type: "POST",
                    data: {}
                }).done(function (response) {
                    if (response.success) {
                        self.isAmexPlaceOrderDisabled(false);
                        self.amexDisabledMessage('');
                    } else {
                        self.isAmexPlaceOrderDisabled(true);
                        self.amexDisabledMessage(response.data.message);
                    }
                    self.updateLoading(false);
                }).fail(function (response) {
                    self.isAmexPlaceOrderDisabled(true);
                    self.amexDisabledMessage($.mage.__('An error occurred while getting Amex details. Please try again.'));
                    self.updateLoading(false);
                });

                return true;
            } catch (e) {
                self.isAmexPlaceOrderDisabled(true);
                self.amexDisabledMessage('');
                self.updateLoading(false);
                return false;
            }
        },
        updateLoading: function(isLoading) {
            this.isLoading = isLoading;
            if (isLoading) {
                fullScreenLoader.startLoader();
            } else {
                fullScreenLoader.stopLoader();
            }
        },
    });
});
