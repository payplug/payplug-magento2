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
            placeCartOrderUrl: 'payplug_payments/applePay/placeCartOrder',
            cancelUrl: 'payplug_payments/payment/cancel',
            returnUrl: 'payplug_payments/payment/paymentReturn',
            session: null,

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
                    const versionNumber = 14;
                    const paymentRequest = this.getPaymentRequest();
                    console.log('paymentRequest');
                    console.log(paymentRequest);
                    this.session = new ApplePaySession(versionNumber, paymentRequest);

                    this.afterPlaceOrder();
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
                console.log('getPaymentRequest');

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
                    },
                    shippingMethods: [
                        {
                            "label": "Standard Shipping",
                            "amount": "10.00",
                            "detail": "Arrives in 5-7 days",
                            "identifier": "standardShipping",
                            "dateComponentsRange": {
                                "startDateComponents": {
                                    "years": 2022,
                                    "months": 9,
                                    "days": 24,
                                    "hours": 0
                                },
                                "endDateComponents": {
                                    "years": 2025,
                                    "months": 9,
                                    "days": 26,
                                    "hours": 0
                                }
                            }
                        },
                        {
                            "label": "Standard Shipping 2",
                            "amount": "5.00",
                            "detail": "Arrives in 99-999 days",
                            "identifier": "standardShipping",
                            "dateComponentsRange": {
                                "startDateComponents": {
                                    "years": 2022,
                                    "months": 9,
                                    "days": 24,
                                    "hours": 0
                                },
                                "endDateComponents": {
                                    "years": 2025,
                                    "months": 9,
                                    "days": 26,
                                    "hours": 0
                                }
                            }
                        },
                    ],
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
            },

            afterPlaceOrder: function() {
                let self = this;
                this.session.onvalidatemerchant = async event => {
                    console.log(event);
                    const eventData = {
                        isTrusted: event.isTrusted,
                        validationURL: event.validationURL,
                        type: event.type,
                    };
                    let encodedEvent = btoa(JSON.stringify(eventData));

                    $.ajax({
                        url: url.build(this.placeCartOrderUrl + '?event=' + encodeURIComponent(encodedEvent)),
                        type: "GET",
                        dataType: 'json',
                        success: function(response) {
                            if (response.error === true) {
                                self.cancelPayplugPayment();
                            } else {
                                try {
                                    self.session.completeMerchantValidation(response.merchand_data);
                                } catch (e) {
                                    self.cancelPayplugPayment();
                                }
                            }
                        },
                        error: function () {
                            self.cancelPayplugPayment();
                        }
                    });
                };
                this.session.onpaymentauthorized = event => {
                    try {
                        $.ajax({
                            url: url.build(self.updateTransactionDataUrl),
                            type: "POST",
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
                                self.cancelPayplugPayment();
                            } else {
                                window.location.replace(url.build(self.returnUrl));
                            }
                        }).fail(function (response) {
                            self.cancelPayplugPayment();
                        });
                    } catch (e) {
                        self.cancelPayplugPayment();
                    }
                };
                this.session.oncancel = event => {
                    self.cancelPayplugPayment();
                };
                this.session.begin();
            },
            cancelPayplugPayment: function() {
                window.location.replace(url.build(this.cancelUrl) + '?form_key=' + $.cookie('form_key'));
            }
        });
    }
);
