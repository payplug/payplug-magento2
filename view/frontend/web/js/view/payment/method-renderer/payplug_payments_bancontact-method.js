define([
    'jquery',
    'ko',
    'mage/url',
    'mage/translate',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/quote',
    'Payplug_Payments/js/action/redirect-on-success'
], function ($, ko, url, $t, Component, fullScreenLoader, quote, redirectOnSuccessAction) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Payplug_Payments/payment/payplug_payments_bancontact'
        },
        redirectAfterPlaceOrder: false,
        isLoading: false,
        isBancontactPlaceOrderDisabled: ko.observable(false),
        bancontactDisabledMessage: ko.observable(''),

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
         * Retrieves the URL for the Bancontact card logo.
         *
         * @returns {String}
         */
        getCardLogo: function () {
            return window.checkoutConfig.payment.payplug_payments_bancontact.logo;
        },

        /**
         * Retrieves the Bancontact information and update the button state.
         *
         * @returns {Boolean}
         */
        updateBancontact: function () {
            const self = this;

            if (this.isLoading) {
                return false;
            }

            this.updateLoading(true);
            this.isBancontactPlaceOrderDisabled(true);
            this.bancontactDisabledMessage('');

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
                        self.isBancontactPlaceOrderDisabled(false);
                    } else {
                        self.bancontactDisabledMessage(response.data.message);
                    }

                    self.updateLoading(false);
                }).fail(function () {
                    self.bancontactDisabledMessage($t('An error occurred while getting Bancontact details. Please try again.'));
                    self.updateLoading(false);
                });

                return true;
            } catch (e) {
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
    });
});
