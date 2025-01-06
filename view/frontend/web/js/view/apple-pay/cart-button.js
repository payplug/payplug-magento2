define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
], function ($, ko, Component, quote) {
        'use strict';

        return Component.extend({
            applePayIsAvailable: false,
            isVisible: ko.observable(false),

            /**
             * Initializes the component.
             *
             * @returns {void}
             */
            initialize: function () {
                this.applePayIsAvailable = this.getApplePayAvailability();
                this.isVisible(this.applePayIsAvailable);
            },

            /**
             * Initializes Apple Pay session.
             *
             * @returns {void}
             */
            initApplePaySession: function() {
                if (this.applePayIsAvailable) {
                    const versionNumber = 3;
                    const paymentRequest = this.getPaymentRequest();
    
                    const session = new ApplePaySession(versionNumber, paymentRequest);
                    session.begin();
                }
            },

            /**
             * Handles button click event.
             *
             * @returns {void}
             */
            handleClick: function () {
                this.initApplePaySession();
            },

            /**
             * Retrieves the locale configuration for Apple Pay.
             *
             * @returns {string} The locale setting from the checkout configuration.
             */
            getApplePayLocale: function() {
                return window.checkoutConfig.payment.payplug_payments_apple_pay.locale;
            },

            /**
             * Checks the availability of Apple Pay.
             *
             * @returns {boolean} True if Apple Pay is available and can make payments, false otherwise.
             */
            getApplePayAvailability: function() {
                return window.ApplePaySession && ApplePaySession.canMakePayments();
            },

            /**
             * Retrieves the payment request data for Apple Pay.
             *
             * @returns {Object} The Apple Pay payment request data.
             */
            getPaymentRequest: function() {
                const totalAmount = this.getTotalAmount();

                const { 
                    domain, 
                    locale,
                    merchand_name
                } = window.checkoutConfig.payment.payplug_payments_apple_pay;
                
                return {
                    countryCode: locale.slice(-2),
                    currencyCode: quote.totals()['quote_currency_code'],
                    merchantCapabilities: ['supports3DS'],
                    supportedNetworks: ['visa', 'masterCard'],
                    total: {
                        label: merchand_name,
                        type: 'final',
                        amount: totalAmount
                    },
                    applicationData: {
                        'apple_pay_domain': btoa(JSON.stringify(domain))
                    }
                };
            },

            /**
             * Calculates the total amount to be paid.
             *
             * @returns {Number} The total amount to be paid.
             */
            getTotalAmount: function() {
                let grandTotal = quote.totals()['grand_total'] + quote.totals()['tax_amount'];
                const totalSegments = quote.totals()['total_segments'];
                
                if (totalSegments) {
                    const totalSegment = totalSegments.find(segment => segment.code.includes('grand_total'));
                    
                    if (totalSegment) {
                        grandTotal = totalSegment.value;
                    }
                }

                return grandTotal;
            }
        });
    }
);