define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'mage/url',
], function ($, ko, Component, quote, url) {
    'use strict';

    return Component.extend({
        applePayIsAvailable: false,
        isVisible: ko.observable(false),
        applePaySession: null,
        placeCartOrderUrl: 'payplug_payments/applePay/placeCartOrder',
        cancelUrl: 'payplug_payments/payment/cancel',
        returnUrl: 'payplug_payments/payment/paymentReturn',

        /**
         * Initializes the component.
         *
         * @returns {void}
         */
        initialize: function () {
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
                const versionNumber = 14;
                const sessionRequest = this._getPaymentRequest();
                this.applePaySession = new ApplePaySession(versionNumber, sessionRequest);
                this._afterPlaceOrder();
            }
        },

        /**
         * Handles the Apple Pay session after the user clicks the "Buy with Apple Pay" button.
         *
         * @private
         * @returns {void}
         */
        _afterPlaceOrder: function() {
            this._bindMarchantValidation();
            this._bindPaymentAuthorization();
            this._bindPaymentCancel();
            this.applePaySession.begin();
        },

        /**
         * Handles button click event.
         *
         * @private
         * @returns {void}
         */
        handleClick: function () {
            this._initApplePaySession();
        },

        /**
         * Retrieves the locale configuration for Apple Pay.
         *
         * @private
         * @returns {string} The locale setting from the checkout configuration.
         */
        _getApplePayLocale: function() {
            return window.checkoutConfig.payment.payplug_payments_apple_pay.locale;
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
         * Retrieves the payment request data for Apple Pay.
         *
         * @private
         * @returns {Object} The Apple Pay payment request data.
         */
        _getPaymentRequest: function() {
            const totalAmount = this._getTotalAmount();

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
         * @private
         * @returns {Number} The total amount to be paid.
         */
        _getTotalAmount: function() {
            let grandTotal = quote.totals()['grand_total'] + quote.totals()['tax_amount'];
            const totalSegments = quote.totals()['total_segments'];
            
            if (totalSegments) {
                const totalSegment = totalSegments.find(segment => segment.code.includes('grand_total'));
                
                if (totalSegment) {
                    grandTotal = totalSegment.value;
                }
            }

            return grandTotal;
        },

        /**
         * Cancels the Payplug payment when the user closes the Apple Pay dialog
         * without making a payment.
         *
         * @private
         * @returns {void}
         */
        _cancelPayplugPayment: function() {
            window.location.replace(url.build(this.cancelUrl) + '?form_key=' + $.cookie('form_key'));
        },

        /**
         * Determines the Apple Pay workflow type based on the current page body class.
         *
         * Apple Pay workflow types are as follows:
         * - 'product': The user is currently on a product page.
         * - 'shopping-cart': The user is currently on the shopping cart page.
         * - 'checkout': The user is currently on the checkout page.
         * - '': The user is on an unknown page.
         *
         * @private
         * @returns {string} The Apple Pay workflow type.
         */
        _getApplePayWorkflowType: function() {
            const bodyClass = $('body').attr('class');
            let workflowType;

            switch (bodyClass) {
                case bodyClass.includes('catalog-product-view'):
                    workflowType = 'product';
                    break;
                case bodyClass.includes('checkout-cart-index'):
                    workflowType = 'shopping-cart';
                    break;
                case bodyClass.includes('checkout-index-index'):
                    workflowType = 'checkout';
                    break;
                default:
                    workflowType = '';
            };

            return workflowType;
        },

        /**
         * Handles the onvalidatemerchant event, which is triggered when the Apple Pay session requires
         * validation of the merchant.
         *
         * @private
         * @returns {void}
         */
        _bindMarchantValidation: function() {
            const self = this;

            this.applePaySession.onvalidatemerchant = async event => {
                const eventData = {
                    isTrusted: event.isTrusted,
                    validationURL: event.validationURL,
                    type: event.type,
                };

                const event = btoa(JSON.stringify(eventData));
                const urlParameters = { event };
                const workflowType = _getApplePayWorkflowType();
                workflowType && (urlParameters.workflowType = workflowType);

                $.ajax({
                    url: url.build(self.placeCartOrderUrl),
                    data: urlParameters,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.error) {
                            this._cancelPayplugPayment();
                        } else {
                            try {
                                self.applePaySession.completeMerchantValidation(response.merchand_data);
                            } catch (e) {
                                this._cancelPayplugPayment();
                            }
                        }
                    },
                    error: function () {
                        this._cancelPayplugPayment();
                    }
                });
            };
        },

        /**
         * Handles the onpaymentauthorized event, which is triggered after the user has authorized
         * the payment with Apple Pay.
         *
         * @private
         * @returns {void}
         */
        _bindPaymentAuthorization: function() {
            const self = this;

            this.applePaySession.onpaymentauthorized = event => {
                try {
                    $.ajax({
                        url: url.build(self.updateTransactionDataUrl),
                        type: 'POST',
                        data: {token: event.payment.token}
                    }).done(function (response) {
                        let applePaySessionStatus = ApplePaySession.STATUS_SUCCESS;

                        if (response.error === true) {
                            applePaySessionStatus = ApplePaySession.STATUS_FAILURE;
                        }
                        self.session.completePayment({
                            "status": applePaySessionStatus
                        });
                        if (response.error === true) {
                            self._cancelPayplugPayment();
                        } else {
                            window.location.replace(url.build(self.returnUrl));
                        }
                    }).fail(function () {
                        self._cancelPayplugPayment();
                    });
                } catch (e) {
                    self._cancelPayplugPayment();
                }
            };
        },

        /**
         * Binds the Apple Pay session's oncancel event to handle payment cancellation.
         *
         * @private
         * @returns {void}
         */
        _bindPaymentCancel: function() {
            this.applePaySession.oncancel = () => {
                this._cancelPayplugPayment();
            };
        },

        /**
         * Handles the onvalidatemerchant event, which is triggered when the Apple Pay session requires
         * validation of the merchant.
         *
         * @private
         * @returns {void}
         */
        _bindMarchantValidation: function() {
            const self = this;

            this.applePaySession.onvalidatemerchant = async event => {
                const eventData = {
                    isTrusted: event.isTrusted,
                    validationURL: event.validationURL,
                    type: event.type,
                };

                const encodedEvent = btoa(JSON.stringify(eventData));

                $.ajax({
                    url: url.build(self.placeCartOrderUrl + '?event=' + encodeURIComponent(encodedEvent)),
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.error) {
                            this._cancelPayplugPayment();
                        } else {
                            try {
                                self.applePaySession.completeMerchantValidation(response.merchand_data);
                            } catch (e) {
                                this._cancelPayplugPayment();
                            }
                        }
                    },
                    error: function () {
                        this._cancelPayplugPayment();
                    }
                });
            };
        },

        /**
         * Handles the onpaymentauthorized event, which is triggered after the user has authorized
         * the payment with Apple Pay.
         *
         * @private
         * @returns {void}
         */
        _bindPaymentAuthorization: function() {
            const self = this;

            this.applePaySession.onpaymentauthorized = event => {
                try {
                    $.ajax({
                        url: url.build(self.updateTransactionDataUrl),
                        type: 'POST',
                        data: {token: event.payment.token}
                    }).done(function (response) {
                        const applePaySessionStatus = ApplePaySession.STATUS_SUCCESS;

                        if (response.error === true) {
                            applePaySessionStatus = ApplePaySession.STATUS_FAILURE;
                        }
                        self.session.completePayment({
                            "status": applePaySessionStatus
                        });
                        if (response.error === true) {
                            self._cancelPayplugPayment();
                        } else {
                            window.location.replace(url.build(self.returnUrl));
                        }
                    }).fail(function () {
                        self._cancelPayplugPayment();
                    });
                } catch (e) {
                    self._cancelPayplugPayment();
                }
            };
        },

        /**
         * Binds the Apple Pay session's oncancel event to handle payment cancellation.
         *
         * @private
         * @returns {void}
         */
        _bindPaymentCancel: function() {
            this.applePaySession.oncancel = () => {
                this._cancelPayplugPayment();
            };
        }
    });
});
