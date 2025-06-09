define([
    'jquery',
    'mage/url',
    'Magento_Ui/js/model/messageList',
    'mage/translate',
    'Magento_Customer/js/customer-data',
], function ($, url, messageList, $t, customerData) {
    'use strict';

    const allowedShippingMethods = 'payplug_payments/applePay/GetAvailablesShippingMethods',
          createMockOrder = 'payplug_payments/applePay/createMockOrder',
          updateCartOrder = 'payplug_payments/applePay/updateCartOrder',
          cancelUrl = 'payplug_payments/applePay/cancelFromButton',
          returnUrl = 'payplug_payments/payment/paymentReturn';

    const payplugApplePay = {
        applePaySession: null,
        orderId: null,
        baseAmount: 0,
        merchandName: null,
        shippingAmount: 0,
        shippingMethod: null,
        isVirtual: false,
        workflowType: null,

        /**
         * Initializes Apple Pay session if it is available.
         *
         * @param {Object} config - The Apple Pay session request configuration.
         *
         * @returns {void}
         */
        initApplePaySession: function (config) {
            const applePayIsAvailable = this.getApplePayAvailability();

            if (applePayIsAvailable) {
                const versionNumber = this._getApplePayVersion();
                const sessionRequest = this.getPaymentRequest(config);
                this.applePaySession = new ApplePaySession(versionNumber, sessionRequest);
                this._afterPlaceOrder();
            }
        },

        /**
         * Checks the availability of Apple Pay.
         *
         * @returns {boolean} True if Apple Pay is available and can make payments, false otherwise.
         */
        getApplePayAvailability: function () {
            return window.ApplePaySession && ApplePaySession.canMakePayments();
        },

        /**
         * Retrieves the Apple Pay version number.
         *
         * If Apple Pay version 14 is supported, returns 14, otherwise returns 3 or 1.
         *
         * @private
         * @returns {number} The Apple Pay version number.
         */
        _getApplePayVersion: function () {
            if (ApplePaySession.supportsVersion(14)) {
                return 14;
            }
            if (ApplePaySession.supportsVersion(3)) {
                return 3;
            }

            return 1;
        },

        /**
         * Constructs the payment request data object for Apple Pay.
         *
         * @param {Object} config - Configuration object containing domain, locale, merchant name, and currency code.
         * @param {string} config.domain - The domain for the Apple Pay session.
         * @param {string} config.locale - The locale used to derive the country code.
         * @param {string} config.merchand_name - The name of the merchant displayed on the payment sheet.
         * @param {string} config.currencyCode - The currency code for the transaction.
         *
         * @returns {Object} The Apple Pay payment request data, including country code, currency code,
         * merchant capabilities, supported networks, and required contact fields.
         */
        getPaymentRequest: function (config) {
            const {
                domain,
                locale,
                merchand_name: merchandName,
                currencyCode
            } = config;

            let paymentRequest = {
                countryCode: locale.slice(-2),
                currencyCode: currencyCode,
                merchantCapabilities: ['supports3DS'],
                supportedNetworks: ['cartesBancaires', 'visa', 'masterCard'],
                supportedTypes: ['debit', 'credit'],
                total: {
                    label: merchandName,
                    type: 'final',
                    amount: this.baseAmount
                },
                applicationData: btoa(JSON.stringify({
                    'apple_pay_domain': domain
                })),
            requiredBillingContactFields: [
                    "postalAddress",
                    "name"
                ]
            };

            if (this.isVirtual === true) {
                paymentRequest.requiredShippingContactFields = [
                    "phone",
                    "email"
                ];
            } else {
                paymentRequest.requiredShippingContactFields = [
                    "postalAddress",
                    "name",
                    "phone",
                    "email"
                ];
            }

            return paymentRequest;
        },

        /**
         * Calculates the total amount to be paid.
         *
         * @private
         * @returns {Number} The total amount to be paid.
         */
        _getTotalAmount: function () {
            return parseFloat(this.baseAmount) + parseFloat(this.shippingAmount);
        },

        /**
         * Handles the Apple Pay session after the user clicks the "Buy with Apple Pay" button.
         *
         * @private
         * @returns {void}
         */
        _afterPlaceOrder: function () {
            this._bindOnCompleteMethod();
            this._bindMarchantValidation();
            this._bindPaymentAuthorization();
            this._bindShippingMethodSelected();
            this._bindShippingContactSelected();
            this._bindPaymentCancel();
            this.applePaySession.begin();
        },

        /**
         * Binds the onpaymentmethodselected event on the Apple Pay session.
         *
         * Called when the user selects a payment method in the Apple Pay payment sheet.
         *
         * @private
         * @returns {void}
         */
        _bindOnCompleteMethod: function () {
            const self = this;

            this.applePaySession.onpaymentmethodselected = function () {
                const updated = {
                    "newTotal": {
                        "label": self.merchandName,
                        "amount": self._getTotalAmount(),
                        "type": "final"
                    }
                };

                self.applePaySession.completePaymentMethodSelection(updated);
            };
        },

        /**
         * Handles the onvalidatemerchant event, which is triggered when the Apple Pay session requires
         * validation of the merchant.
         *
         * @private
         * @returns {void}
         */
        _bindMarchantValidation: function () {
            const self = this;

            this.applePaySession.onvalidatemerchant = async event => {
                const eventData = {
                    isTrusted: event.isTrusted,
                    validationURL: event.validationURL,
                    type: event.type,
                };

                let formData = new FormData();

                if (this.workflowType === 'product') {
                    const form = $('#product_addtocart_form');
                    formData = new FormData(form[0]);
                }

                formData.set('btoaevent',  btoa(JSON.stringify(eventData)));

                $.ajax({
                    url: url.build(createMockOrder) + '?form_key=' + $.cookie('form_key'),
                    data: formData,
                    type: 'POST',
                    contentType: false,
                    processData: false,
                    success: function (response) {
                        if (response.error) {
                            self._cancelPayplugPaymentWithAbort();
                        } else {
                            try {
                                if (self.workflowType === 'product') {
                                    self.invalidateMiniCart(true);
                                    self.setBaseAmount(response.base_amount);
                                }

                                self.orderId = response.order_id;
                                self.applePaySession.completeMerchantValidation(response.merchantSession);
                            } catch (e) {
                                self._cancelPayplugPaymentWithAbort();
                            }
                        }
                    },
                    error: function () {
                        self._cancelPayplugPaymentWithAbort();
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
        _bindPaymentAuthorization: function () {
            const self = this;

            this.applePaySession.onpaymentauthorized = event => {
                try {
                    $.ajax({
                        url: url.build(updateCartOrder) + '?form_key=' + $.cookie('form_key'),
                        type: 'POST',
                        data: {
                            token: event.payment.token,
                            billing: event.payment.billingContact,
                            shipping: event.payment.shippingContact,
                            shipping_method: self.shippingMethod,
                            order_id: self.orderId,
                            workflowType: self.workflowType
                        }
                    }).done(function (response) {
                        if (response.error === true) {
                            self._cancelPayplugPaymentWithFailure();
                        } else {
                            self.applePaySession.completePayment({
                                "status": ApplePaySession.STATUS_SUCCESS
                            });

                            self.invalidateMiniCart();

                            window.location.replace(url.build(returnUrl));
                        }
                    }).fail(function () {
                        self._cancelPayplugPaymentWithFailure();
                    });
                } catch (e) {
                    self._cancelPayplugPaymentWithFailure();
                }
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

                self.shippingMethod = shippingEvent.shippingMethod.identifier;
                self.shippingAmount = shippingEvent.shippingMethod.amount;

                const updated = {
                    "newTotal": {
                        "label": self.merchandName,
                        "amount": self._getTotalAmount(),
                        "type": "final",
                        "paymentTiming": "immediate",
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
                let contact = shippingContactEvent.shippingContact;
                if (contact?.countryCode) {
                    contact.countryCode = contact.countryCode.toUpperCase();
                }

                $.ajax({
                    url: url.build(allowedShippingMethods) + '?form_key=' + $.cookie('form_key'),
                    data: shippingContactEvent.shippingContact,
                    type: 'POST',
                    dataType: 'json',
                    success: function (response) {
                        if (response.error) {
                            self._cancelPayplugPaymentWithAbort();
                        } else {
                            try {
                                let errors = [];

                                if (response.methods.length === 0) {
                                    const applePayError = new ApplePayError(
                                        'shippingContactInvalid',
                                        'postalAddress',
                                        $t("Delivery unavailable to this address")
                                    );
                                    errors.push(applePayError);
                                }

                                const updated = {
                                    "errors": errors,
                                    "newTotal": {
                                        "label": self.merchandName,
                                        "amount": self._getTotalAmount(),
                                        "type": "final"
                                    },
                                    "newShippingMethods": response.methods,
                                };
                                self.applePaySession.completeShippingContactSelection(updated);
                            } catch (e) {
                                self._cancelPayplugPaymentWithAbort();
                            }
                        }
                    },
                    error: function () {
                        self._cancelPayplugPaymentWithAbort();
                    }
                });
            };
        },

        /**
         * Binds the Apple Pay session's oncancel event to handle payment cancellation.
         *
         * @private
         * @returns {void}
         */
        _bindPaymentCancel: function () {
            this.applePaySession.oncancel = () => {
                this._cancelPayplugPayment();
            };
        },

        /**
         * Sets the base amount to pay.
         *
         * @param {Number} amount the base amount to pay
         * @returns {void}
         */
        setBaseAmount: function (amount) {
            this.baseAmount = amount;
        },

        /**
         * Sets whether the current order is virtual or not.
         *
         * @param {Boolean} isVirtual whether the current order is virtual or not
         * @returns {void}
         */
        setIsVirtual: function (isVirtual) {
            this.isVirtual = isVirtual;
        },

        /**
         * Sets the merchant name.
         *
         * @param {String} merchandName - The merchant name to be set.
         * @returns {void}
         */
        setMerchandName: function (merchandName) {
            this.merchandName = merchandName;
        },

        /**
         * Sets the workflow type for the current process.
         *
         * @param {String} workflowType - The type of workflow to be set.
         * @returns {void}
         */
        setWorkflowType: function (workflowType) {
            this.workflowType = workflowType;
        },

        /**
         * Clear order data
         *
         * @returns {void}
         */
        clearOrderData: function () {
            this.orderId = null;
            this.baseAmount = 0;
            this.shippingAmount = 0;
            this.shippingMethod = null;
            this.isVirtual = false;
        },

        /**
         * Invalidate minicart
         *
         * @returns {void}
         */
        invalidateMiniCart: function (withReload = false) {
            customerData.invalidate(['cart']);

            if (withReload) {
                customerData.reload(['cart'], true);
            }
        },

        /**
         * Cancel the payment with abort (close Payment UI)
         *
         * @private
         * @returns {void}
         */
        _cancelPayplugPaymentWithAbort: function () {
            this._cancelPayplugPayment();
            this.applePaySession.abort();
        },
        
        /**
         * Cancel the payment with failure (close Payment UI and trigger ApplePay Session failure)
         *
         * @private
         * @returns {void}
         */
        _cancelPayplugPaymentWithFailure: function () {
            this._cancelPayplugPayment();
            this.applePaySession.completePayment({
                "status": ApplePaySession.STATUS_FAILURE
            });
        },

        /**
         * Call payment cancellation URL, then close ApplePaySession
         *
         * @private
         * @returns {void}
         */
        _cancelPayplugPayment: function () {
            const self = this;

            if (this.orderId) {
                $.ajax({
                    url: url.build(cancelUrl) + '?form_key=' + $.cookie('form_key'),
                    type: 'GET'
                }).always(function () {
                    self.invalidateMiniCart(true);
                });
            }

            messageList.addErrorMessage({ message: $t('The transaction was aborted and your card has not been charged') });
        }
    };

    return payplugApplePay;
});
