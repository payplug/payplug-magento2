define([
    'jquery',
    'ko',
    'mage/translate',
    'mage/url',
    'Magento_Checkout/js/view/payment/default',
    'Payplug_Payments/js/action/redirect-on-success',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/quote'
], function ($, ko, $t, url, Component, redirectOnSuccessAction, fullScreenLoader, quote) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Payplug_Payments/payment/payplug_payments_ppro'
        },
        redirectAfterPlaceOrder: false,
        isLoading: false,

        /**
         * Init component
         *
         * @return {Object}
         */
        initialize: function () {
            const self = this;

            this._super();

            quote.paymentMethod.subscribe(function (value) {
                self.isLoading = false;
                if (value && value.method === self.getCode()) {
                    self.updatePproMethod();
                }
            });

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

        /**
         * Init observable variables
         * @return {Object}
         */
        initObservable: function () {
            this._super();

            this.observe({
                'isPproPlaceOrderDisabled': false,
                'pproDisabledMessage': '',
                'pproErrorType': '',
            });

            this.isActive = ko.computed(function () {
                return this.getCode() === this.isChecked() && '_active';
            }, this);

            return this;
        },

        /**
         * Triggered after a payment has been placed.
         *
         * @returns void
         */
        afterPlaceOrder: function () {
            fullScreenLoader.stopLoader();
            redirectOnSuccessAction.execute();
        },

        /**
         * Retrieves the URL for the Ppro card logo.
         *
         * @returns {String}
         */
        getLogo: function () {
            return this.getConfiguration().logo;
        },

        /**
         *
         * @returns {Boolean} True if the request was sent successfully, false otherwise.
         */
        updatePproMethod: function () {
            const self = this;

            if (this.isLoading) {
                return false;
            }

            this.updateLoading(true);

            try {
                $.ajax({
                    url: url.build('payplug_payments/apm/isAvailable'),
                    type: 'POST',
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
                        self.processPproFailure(response.data.message, response.data.type);
                    }

                    self.updateLoading(false);
                }).fail(function () {
                    self.processPproFailure($t('An error occurred. Please try again.'));
                    self.updateLoading(false);
                });

                return true;
            } catch (e) {
                self.processPproFailure($t('An error occurred. Please try again.'));
                self.updateLoading(false);

                return false;
            }
        },

        /**
         * Update the loading state of the payment method.
         *
         * @param {Boolean} isLoading
         * @returns void
         */
        updateLoading: function (isLoading) {
            this.isLoading = isLoading;

            if (isLoading) {
                fullScreenLoader.startLoader();
            } else {
                fullScreenLoader.stopLoader();
            }
        },

        /**
         * Handles a failure when checking if the Ppro payment method is available.
         *
         * @param {String} message
         * @param {String} errorType
         * @returns void
         */
        processPproFailure: function (message, errorType) {
            this.isPproPlaceOrderDisabled(true);
            this.pproDisabledMessage(message);
            this.pproErrorType(typeof errorType === 'undefined' ? '' : errorType);
        },

        /**
         * Returns the label associated with the given payment type.
         *
         * @returns {String}
         */
        getConfiguration: function () {
            return {};
        }
    });
});
