define([
    'jquery',
    'ko',
    'uiComponent',
    'mage/url',
], function ($, ko, Component, url) {
    'use strict';

    return Component.extend({
        isVisible: ko.observable(false),
        workflowType: '',

        /**
         * Initializes the component.
         *
         * @returns {void}
         */
        initialize: function () {
            this._super();
            this.applePayIsAvailable = this._getApplePayAvailability();
            this.isVisible(this.applePayIsAvailable);
        },

        /**
         * Initializes Apple Pay session.
         *
         * @private
         * @returns {void}
         */
        _initApplePaySession: function() {
            if (this.applePayIsAvailable) {
                const versionNumber = this._getApplePayVersion();
                const sessionRequest = this._getPaymentRequest();
                this.applePaySession = new ApplePaySession(versionNumber, sessionRequest);
            }
        },

        /**
         * Handles button click event.
         *
         * @returns {void}
         */
        handleClick: function () {
            this._initApplePaySession();
        },

        /**
         * Checks the availability of Apple Pay.
         *
         * @private
         * @returns {boolean} True if Apple Pay is available and can make payments, false otherwise.
         */
        _getApplePayAvailability: function() {
            return window.ApplePaySession && ApplePaySession.canMakePayments();
        },

        /**
         * Retrieves the Apple Pay version number.
         *
         * If Apple Pay version 18 is supported, returns 18, otherwise returns 14 or 3 or 1.
         *
         * @private
         * @returns {number} The Apple Pay version number.
         */
        _getApplePayVersion: function() {
            if (ApplePaySession.supportsVersion(18)) {
                return 18;
            }
            if (ApplePaySession.supportsVersion(14)) {
                return 14;
            }
            if (ApplePaySession.supportsVersion(3)) {
                return 3;
            }

            return 1;
        },

        /**
         * Retrieves the payment request data for Apple Pay.
         *
         * @private
         * @returns {Object} The Apple Pay payment request data.
         */
        _getPaymentRequest: function() {
            const totalAmount = this._getTotalAmount();
            this.amount = totalAmount;

            // @todo : dynamize values
            const domain = '';
            const locale = '';
            const merchand_name = '';
            const currencyCode = '';

            return {
                countryCode: locale,
                currencyCode: currencyCode,
                merchantCapabilities: ['supports3DS'],
                supportedNetworks: ['cartesBancaires', 'visa', 'masterCard'],
                supportedTypes: ['debit', 'credit'],
                total: {
                    label: merchand_name,
                    type: 'final',
                    amount: totalAmount
                },
                applicationData: btoa(JSON.stringify({
                    'apple_pay_domain': domain
                })),
                shippingType: "shipping",
                requiredBillingContactFields: [
                    "postalAddress",
                    "name"
                ],
                requiredShippingContactFields: [
                    "postalAddress",
                    "name",
                    "phone",
                    "email"
                ],
            };
        },

        /**
         * Calculates the total amount to be paid.
         *
         * @private
         * @returns {Number} The total amount to be paid.
         */
        _getTotalAmount: function() {
            // @todo product amount
            let grandTotal = 0;

            return grandTotal;
        },

        /**
         * Redirects the user to the payment cancellation URL.
         *
         * @private
         * @returns {void}
         */
        _cancelPayplugPayment: function () {
            window.location.replace(url.build(this.cancelUrl) + '?form_key=' + $.cookie('form_key'));
        }
    });
});
