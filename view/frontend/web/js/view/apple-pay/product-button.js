define([
    'jquery',
    'ko',
    'uiComponent',
    'mage/url',
], function ($, ko, Component, url) {
    'use strict';

    return Component.extend({
        applePayIsAvailable: false,
        isVisible: ko.observable(false),
        applePaySession: null,
        order_id: null,
        allowedShippingMethods: 'payplug_payments/applePay/GetAvailablesShippingMethods',
        createMockOrder: 'payplug_payments/applePay/createMockOrder',
        updateCartOrder: 'payplug_payments/applePay/updateCartOrder',
        createQuote: 'payplug_payments/applePay/CreateApplePayQuote',
        cancelUrl: 'payplug_payments/payment/cancel',
        returnUrl: 'payplug_payments/payment/paymentReturn',
        amount: null,
        base_amount: 0,
        shipping_amount: 0,
        shipping_method: null,
        workflowType: '',

        /**
         * Initializes the component.
         *
         * @returns {void}
         */
        initialize: function () {
            this._super();
            this.merchandName = this.applePayConfig.merchand_name;
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
                this._afterPlaceOrder();
            }
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
         * Handles button click event.
         * Create a new quote from the product in the product page
         * And then init Apple Pay Session
         * The async is required as ApplePaySession MUST be created from a user gesture
         *
         * @returns {void}
         */
        handleClick: async function () {
            try {
                const productId = $('#product_addtocart_form input[name=product]').val();
                const qty = $('#qty').val() || 1;
                const superAttribute = {};//TODO for the configurable

                $('#product_addtocart_form select.super-attribute-select').each(function () {
                    const attributeId = $(this).attr('name').match(/\d+/)[0];
                    const optionId = $(this).val();
                    if (optionId) {
                        superAttribute[attributeId] = optionId;
                    }
                });

                const response = await $.post(url.build(this.createQuote), {
                    product_id: productId,
                    qty: qty,
                    super_attribute: superAttribute,
                    form_key: $.cookie('form_key')
                });

                if (response.success) {
                    this.base_amount = parseFloat(response.base_amount);
                    this._initApplePaySession();
                } else {
                    alert(response.message || 'Could not create quote for Apple Pay');
                }
            } catch (err) {
                alert('Error preparing Apple Pay quote');
            }
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
            const domain = this.applePayConfig.domain;
            const locale = this.applePayConfig.locale;
            const merchand_name = this.applePayConfig.merchand_name;
            const currencyCode = this.applePayConfig.currency;

            return {
                countryCode: locale.slice(-2),
                currencyCode: currencyCode,
                merchantCapabilities: ['supports3DS'],
                supportedNetworks: ['cartesBancaires', 'visa', 'masterCard'],
                supportedTypes: ['debit', 'credit'],
                total: {
                    label: merchand_name,
                    type: 'final',
                    amount: this.base_amount
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
            return parseFloat(this.base_amount) + parseFloat(this.shipping_amount);
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
                            shipping_method: self.shipping_method,
                            order_id: self.order_id,
                            workflowType: self.workflowType
                        }
                    }).done(function (response) {
                        if (response.error === true) {
                            self._cancelPayplugPayment();
                        } else {
                            self.applePaySession.completePayment({
                                "status": ApplePaySession.STATUS_SUCCESS
                            });

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

                self.shipping_method = shippingEvent.shippingMethod.identifier;
                self.shipping_amount = shippingEvent.shippingMethod.amount;

                const updated = {
                    "newTotal": {
                        "label": self.merchandName,
                        "amount": self._getTotalAmount(),
                        "type": "final"
                    }
                }

                self.applePaySession.completeShippingMethodSelection(updated);
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
                                const updated = {
                                    "newTotal": {
                                        "label": self.merchandName,
                                        "amount": self._getTotalAmount(),
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
         * Call payment cancellation URL, then close ApplePaySession
         *
         * @private
         * @returns {void}
         */
        _cancelPayplugPayment: function () {
            $.ajax(url.build(this.cancelUrl) + '?form_key=' + $.cookie('form_key'));

            this.applePaySession.completePayment({
                "status": ApplePaySession.STATUS_FAILURE
            });
        }
    });
});
