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
            template: 'Payplug_Payments/payment/payplug_payments_ppro'
        },

        redirectAfterPlaceOrder: false,
        isLoading: false,
        isPproPlaceOrderDisabled: ko.observable(false),
        pproDisabledMessage: ko.observable(''),

        initialize: function () {
            var self = this;

            this._super();
            quote.paymentMethod.subscribe(function (value) {
                self.isLoading = false;
                if (value && value.method === self.getCode()) {
                    self.updatePproMethod();
                }
            });
            if (quote.paymentMethod() && quote.paymentMethod().method === self.getCode()) {
                self.updatePproMethod();
            }
            quote.shippingAddress.subscribe(function () {
                if (self.getCode() === self.isChecked()) {
                    self.updatePproMethod();
                }
            });
            quote.billingAddress.subscribe(function () {
                if (quote.billingAddress() !== null) {
                    if (self.getCode() === self.isChecked()) {
                        self.updatePproMethod();
                    }
                }
            });
            quote.totals.subscribe(function () {
                if (self.getCode() === self.isChecked()) {
                    self.updatePproMethod();
                }
            });
            quote.shippingMethod.subscribe(function () {
                if (self.getCode() === self.isChecked()) {
                    self.updatePproMethod();
                }
            });

            return this;
        },

        afterPlaceOrder: function () {
            fullScreenLoader.stopLoader();
            redirectOnSuccessAction.execute();
        },
        getLogo: function() {
            return this.getConfiguration().logo;
        },
        updatePproMethod: function() {
            var self = this;
            if (self.isLoading) {
                return false;
            }
            self.updateLoading(true);
            try {
                $.ajax({
                    url: urlBuilder.build('payplug_payments/ppro/isAvailable'),
                    type: "POST",
                    data: {
                        'billingCountry': quote.billingAddress() ? quote.billingAddress().countryId : null,
                        'shippingCountry': quote.shippingAddress() ? quote.shippingAddress().countryId : null,
                        'paymentMethod': self.getCode(),
                    }
                }).done(function (response) {
                    if (response.success) {
                        self.isPproPlaceOrderDisabled(false);
                        self.pproDisabledMessage('');
                    } else {
                        self.processPproFailure(response.data.message);
                    }
                    self.updateLoading(false);
                }).fail(function (response) {
                    self.processPproFailure($.mage.__('An error occurred. Please try again.'));
                    self.updateLoading(false);
                });

                return true;
            } catch (e) {
                self.processPproFailure($.mage.__('An error occurred. Please try again.'));
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
        processPproFailure: function(message) {
            this.isPproPlaceOrderDisabled(true);
            this.pproDisabledMessage(message);
        },
        // Functions to be defined by each ppro payment renderer
        getConfiguration: function() {
            return {};
        },
    });
});
