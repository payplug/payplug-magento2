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
        order_id: null,
        createMockOrder: 'payplug_payments/applePay/createMockOrder',
        updateCartOrder: 'payplug_payments/applePay/updateCartOrder',
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
            this._bindShippingMethodSelected();
            this._bindPaymentCancel();
            this.applePaySession.begin();
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

                let btoaevent = btoa(JSON.stringify(eventData));
                const urlParameters = { btoaevent };
                const workflowType = self._getApplePayWorkflowType();
                workflowType && (urlParameters.workflow_type = workflowType);

                $.ajax({
                    url: url.build(self.createMockOrder),
                    data: urlParameters,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.error) {
                            self._cancelPayplugPayment();
                        } else {
                            try {
                                self.order_id = response.order_id;
                                self.applePaySession.completeMerchantValidation(response.merchantSession);
                            } catch (e) {
                                self._cancelPayplugPayment();
                            }
                        }
                    },
                    error: function () {
                        self._cancelPayplugPayment();
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
                        url: url.build(self.updateCartOrder),
                        type: 'POST',
                        data: {
                            token: event.payment.token,
                            billing: event.payment.billingContact,
                            shipping: event.payment.shippingContact,
                            order_id: self.order_id
                        }
                    }).done(function (response) {
                        console.log(response);
                        console.log('----------');
                        let applePaySessionStatus = ApplePaySession.STATUS_SUCCESS;

                        if (response.error === true) {
                            applePaySessionStatus = ApplePaySession.STATUS_FAILURE;
                        }
                        self.applePaySession.completePayment({
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
         * Update the new total when the shipping method is updated.
         *
         * @private
         * @returns {void}
         */
        _bindShippingMethodSelected: function() {
            this.applePaySession.onshippingmethodselected = shippingEvent => {
                let amount = shippingEvent.shippingMethod.amount;
                let label = shippingEvent.shippingMethod.label;
                //TODO doing so is overriding the actual price of the items in cart by the shipping method 37e become like 5 or 10e
                //Must find a way for the amount to be the actual final price
                const updated = {
                    "newTotal": {
                        "label": label,
                        "amount": amount,
                        "type": "final"
                    },
                }
                this.applePaySession.completeShippingMethodSelection(updated);
            };
        },

        /**
         * Redirects the user to the payment cancellation URL.
         *
         * @private
         * @returns {void}
         */
        _cancelPayplugPayment: function() {
            window.location.replace(url.build(this.cancelUrl) + '?form_key=' + $.cookie('form_key'));
        }
    });
});
