/* @api */
define([
    'jquery',
    'ko',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/view/payment/default',
    'Payplug_Payments/js/apple-pay'
], function ($, ko, fullScreenLoader, quote, Component, payplugApplePay) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Payplug_Payments/payment/payplug_payments_apple_pay'
        },
        applePayIsAvailable: false,
        isVisible: ko.observable(false),
        isLoading: false,
        applePayDisabledMessage: ko.observable(''),

        /**
         * Initializes the component.
         *
         * @returns {void}
         */
        initialize: function () {
            this._super();
            const self = this;

            this.applePayIsAvailable = payplugApplePay.getApplePayAvailability();
            this.isVisible(this.applePayIsAvailable);

            quote.paymentMethod.subscribe(function (value) {
                self.isLoading = false;
                if (value && value.method === self.getCode()) {
                    self.updateApplePayAvailability();
                }
            });

            quote.shippingAddress.subscribe(function () {
                if (self.getCode() === self.isChecked()) {
                    self.updateApplePayAvailability();
                }
            });

            quote.billingAddress.subscribe(function () {
                if (quote.billingAddress() !== null) {
                    if (self.getCode() === self.isChecked()) {
                        self.updateApplePayAvailability();
                    }
                }
            });

            quote.totals.subscribe(function () {
                if (self.getCode() === self.isChecked()) {
                    self.updateApplePayAvailability();
                }
            });

            quote.shippingMethod.subscribe(function () {
                if (self.getCode() === self.isChecked()) {
                    self.updateApplePayAvailability();
                }
            });

            return this;
        },
        /**
         * Checks if Apple Pay is available and updates the button and message accordingly.
         *
         * @returns {boolean} True if the request was sent successfully, false otherwise.
         */
        updateApplePayAvailability: function() {
            var self = this;

            if (self.isLoading) {
                return false;
            }

            $('apple-pay-button').off('click');
            this.applePayDisabledMessage('');

            try {
                $.ajax({
                    url: url.build(this.isAvailableUrl),
                    type: "POST",
                    data: {}
                }).done(function (response) {
                    if (response.success) {
                    } else {
                        self.applePayDisabledMessage(response.data.message);
                    }
                }).fail(function () {
                    self.applePayDisabledMessage($.mage.__('An error occurred while getting Apple Pay details. Please try again.'));
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

        /**
         * Handles button click event.
         *
         * @returns {void}
         */
        handleClick: function () {
            if (this.applePayIsAvailable) {
                payplugApplePay.initApplePaySession();
            }
        },
        
        /**
         * Retrieves the card logo associated with Apple Pay.
         *
         * @returns {string} The logo for Apple Pay.
         */
        getCardLogo: function() {
            return window.checkoutConfig.payment.payplug_payments_apple_pay.logo;
        },
    });
});
