define([
    'jquery',
    'Magento_Checkout/js/model/quote',
    'mage/url',
], function ($, quote, url) {
    'use strict';

    return {
        applePayIsAvailable: false,
        applePaySession: null,
        order_id: null,
        allowedShippingMethods: 'payplug_payments/applePay/GetAvailablesShippingMethods',
        createMockOrder: 'payplug_payments/applePay/createMockOrder',
        updateCartOrder: 'payplug_payments/applePay/updateCartOrder',
        cancelUrl: 'payplug_payments/payment/cancel',
        returnUrl: 'payplug_payments/payment/paymentReturn',
        amount: null,
        workflowType: null,

        /**
         * Initializes Apple Pay session.
         *
         * @returns {void}
         */
        initApplePaySession: function() {
            const versionNumber = 14;
            const sessionRequest = this._getPaymentRequest();
            this.applePaySession = new ApplePaySession(versionNumber, sessionRequest);
            this._afterPlaceOrder();
        },

        /**
         * Handles the Apple Pay session after the user clicks the "Buy with Apple Pay" button.
         *
         * @private
         * @returns {void}
         */
        _afterPlaceOrder: function () {
            this._bindMarchantValidation();
            this._bindPaymentAuthorization();
            this._bindShippingMethodSelected();
            this._bindPaymentCancel();
            this._bindShippingContactSelected();
            this.applePaySession.begin();
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
         * @private
         * @returns {Object} The Apple Pay payment request data.
         */
        _getPaymentRequest: function() {
            const totalAmount = this._getTotalAmount();
            this.amount = totalAmount;

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
         * Calculates the total amount to be paid without shipping methods
         *
         * @private
         * @returns {Number} The total amount to be paid without shipping methods.
         */
        _getTotalAmountNoShipping: function() {
            let shippingAmount = quote.totals()['shipping_amount'];
            let grandTotal = this._getTotalAmount();

            return (parseFloat(grandTotal) - parseFloat(shippingAmount));
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

            if (bodyClass.includes('catalog-product-view')) {
                return 'product';
            }

            else if (bodyClass.includes('checkout-cart-index')) {
                return 'shopping-cart';
            }

            else if (bodyClass.includes('checkout-index-index')) {
                return 'checkout';
            }

            else {
                return '';
            }
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
                self.workflowType = self._getApplePayWorkflowType();

                $.ajax({
                    url: url.build(self.createMockOrder) + '?form_key=' + $.cookie('form_key'),
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
                        url: url.build(self.updateCartOrder) + '?form_key=' + $.cookie('form_key'),
                        type: 'POST',
                        data: {
                            token: event.payment.token,
                            billing: event.payment.billingContact,
                            shipping: event.payment.shippingContact,
                            amount: self.amount,
                            order_id: self.order_id,
                            workflowType: self.workflowType
                        }
                    }).done(function (response) {
                        console.log(response);
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
        _bindShippingMethodSelected: function () {
            const self = this;
            this.applePaySession.onshippingmethodselected = shippingEvent => {
                if (typeof shippingEvent === 'undefined') {
                    return;
                }
                let amount = parseFloat(self._getTotalAmountNoShipping()) + parseFloat(shippingEvent.shippingMethod.amount);
                self.amount = amount;
                const updated = {
                    "newTotal": {
                        "label": window.checkoutConfig.payment.payplug_payments_apple_pay.merchand_name,
                        "amount": amount,
                        "type": "final"
                    },
                }
                this.applePaySession.completeShippingMethodSelection(updated);
            };
        },

        /**
         * Update shipping methods list when a contact is selected
         *
         * @private
         * @returns {void}
         */
        _bindShippingContactSelected: function () {
            const self = this;
            this.applePaySession.onshippingcontactselected = async shippingContactEvent => {
                $.ajax({
                    url: url.build(self.allowedShippingMethods) + '?form_key=' + $.cookie('form_key'),
                    data: shippingContactEvent.shippingContact,
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        if (response.error) {
                            self._cancelPayplugPayment();
                        } else {
                            try {
                                let amount = parseFloat(self._getTotalAmountNoShipping());
                                self.amount = amount;
                                const updated = {
                                    "newTotal": {
                                        "label": window.checkoutConfig.payment.payplug_payments_apple_pay.merchand_name,
                                        "amount": amount,
                                        "type": "final"
                                    },
                                    "newShippingMethods": response.methods,
                                };
                                self.applePaySession.completeShippingContactSelection(updated);
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
         * Redirects the user to the payment cancellation URL.
         *
         * @private
         * @returns {void}
         */
        _cancelPayplugPayment: function () {
            window.location.replace(url.build(this.cancelUrl) + '?form_key=' + $.cookie('form_key'));
        }
    };
});
