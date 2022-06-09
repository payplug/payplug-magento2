/* @api */
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
            template: 'Payplug_Payments/payment/payplug_payments_bancontact'
        },

        redirectAfterPlaceOrder: false,
        isLoading: false,
        isBancontactPlaceOrderDisabled: ko.observable(false),
        bancontactDisabledMessage: ko.observable(''),

        initialize: function () {
            var self = this;

            this._super();
            quote.paymentMethod.subscribe(function (value) {
                self.isLoading = false;
                if (value && value.method === self.getCode()) {
                    self.updateBancontact();
                }
            });
            quote.shippingAddress.subscribe(function () {
                if (self.getCode() === self.isChecked()) {
                    self.updateBancontact();
                }
            });
            quote.billingAddress.subscribe(function () {
                if (quote.billingAddress() !== null) {
                    if (self.getCode() === self.isChecked()) {
                        self.updateBancontact();
                    }
                }
            });
            quote.totals.subscribe(function () {
                if (self.getCode() === self.isChecked()) {
                    self.updateBancontact();
                }
            });
            quote.shippingMethod.subscribe(function () {
                if (self.getCode() === self.isChecked()) {
                    self.updateBancontact();
                }
            });

            return this;
        },

        afterPlaceOrder: function () {
            fullScreenLoader.stopLoader();
            redirectOnSuccessAction.execute();
        },
        getCardLogo: function() {
            return window.checkoutConfig.payment.payplug_payments_bancontact.logo;
        },
        updateBancontact: function() {
            var self = this;
            if (self.isLoading) {
                return false;
            }
            self.updateLoading(true);
            this.isBancontactPlaceOrderDisabled(true);
            this.bancontactDisabledMessage('');
            try {
                $.ajax({
                    url: urlBuilder.build('payplug_payments/bancontact/isAvailable'),
                    type: "POST",
                    data: {}
                }).done(function (response) {
                    if (response.success) {
                        self.isBancontactPlaceOrderDisabled(false);
                    } else {
                        self.bancontactDisabledMessage(response.data.message);
                    }
                    self.updateLoading(false);
                }).fail(function (response) {
                    self.bancontactDisabledMessage($.mage.__('An error occurred while getting Bancontact details. Please try again.'));
                    self.updateLoading(false);
                });

                return true;
            } catch (e) {
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
