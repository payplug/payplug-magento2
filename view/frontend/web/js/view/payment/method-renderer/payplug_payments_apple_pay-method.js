/* @api */
define([
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/full-screen-loader',
    'jquery',
    'Magento_Checkout/js/model/quote',
    'mage/url',
    'ko'
], function (Component, fullScreenLoader, $, quote, url, ko) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Payplug_Payments/payment/payplug_payments_apple_pay'
        },

        redirectAfterPlaceOrder: false,
        isVisible: ko.observable(false),
        cancelUrl: 'payplug_payments/payment/cancel',
        returnUrl: 'payplug_payments/payment/paymentReturn',
        getTransactionDataUrl: 'payplug_payments/applePay/getTransactionData',
        updateTransactionDataUrl: 'payplug_payments/applePay/updateTransaction',
        isAvailableUrl: 'payplug_payments/applePay/isAvailable',
        session: null,
        isLoading: false,
        applePayDisabledMessage: ko.observable(''),

        initialize: function () {
            this._super();

            let self = this;
            this.isVisible(window.ApplePaySession && ApplePaySession.canMakePayments());
            quote.paymentMethod.subscribe(function (value) {
                self.isLoading = false;
                if (value && value.method === self.getCode()) {
                    self.updateApplePayAvailability();
                }
            });
            quote.shippingAddress.subscribe(function () {
                if (self.getCode() === self.isChecked()) {
                    self.updateApplePayAvailability();
                }
            });
            quote.billingAddress.subscribe(function () {
                if (quote.billingAddress() !== null) {
                    if (self.getCode() === self.isChecked()) {
                        self.updateApplePayAvailability();
                    }
                }
            });
            quote.totals.subscribe(function () {
                if (self.getCode() === self.isChecked()) {
                    self.updateApplePayAvailability();
                }
            });
            quote.shippingMethod.subscribe(function () {
                if (self.getCode() === self.isChecked()) {
                    self.updateApplePayAvailability();
                }
            });

            return this;
        },
        updateApplePayAvailability: function() {
            var self = this;
            if (self.isLoading) {
                return false;
            }
            self.updateLoading(true);
            this.unbindButtonClick();
            this.applePayDisabledMessage('');
            try {
                $.ajax({
                    url: url.build(this.isAvailableUrl),
                    type: "POST",
                    data: {}
                }).done(function (response) {
                    if (response.success) {
                        self.bindButtonClick();
                    } else {
                        self.applePayDisabledMessage(response.data.message);
                    }
                    self.updateLoading(false);
                }).fail(function (response) {
                    self.applePayDisabledMessage($.mage.__('An error occurred while getting Apple Pay details. Please try again.'));
                    self.updateLoading(false);
                });

                return true;
            } catch (e) {
                self.updateLoading(false);
                return false;
            }
        },
        updateLoading: function(isLoading) {
            this.isLoading = isLoading;
            if (isLoading) {
                fullScreenLoader.startLoader();
            } else {
                fullScreenLoader.stopLoader();
            }
        },
        bindButtonClick: function() {
            setTimeout(function() {
                $('apple-pay-button').click(function(data, event) {
                    this.placeOrder(data, event);
                }.bind(this));
            }.bind(this), 1);
        },
        unbindButtonClick: function() {
            $('apple-pay-button').off('click');
        },
        placeOrder: function (data, event) {
            if (this.isPlaceOrderActionAllowed() === true) {
                this.unbindButtonClick();
                let grandTotal = quote.totals()['grand_total'] + quote.totals()['tax_amount'];
                if (quote.totals()['total_segments']) {
                    let totalSegment = quote.totals()['total_segments'].filter(function (segment) {
                        return segment.code.indexOf('grand_total') !== -1;
                    });
                    if (totalSegment.length === 1) {
                        grandTotal = totalSegment[0].value;
                    }
                }
                let request = {
                    "countryCode": quote.billingAddress() ? quote.billingAddress().countryId : '',
                    "currencyCode": quote.totals()['quote_currency_code'],
                    "merchantCapabilities": [
                        "supports3DS"
                    ],
                    "supportedNetworks": [
                        "visa", "masterCard"
                    ],
                    "total": {
                        "label": window.checkoutConfig.payment.payplug_payments_apple_pay.merchand_name,
                        "type": "final",
                        "amount": grandTotal
                    },
                    'applicationData': btoa(JSON.stringify({
                        'apple_pay_domain': window.checkoutConfig.payment.payplug_payments_apple_pay.domain
                    }))
                };
                this.session = new ApplePaySession(3, request);

                let placeOrderResult = this._super(data, event);
                this.bindButtonClick();

                return placeOrderResult;
            }

            return false;
        },
        afterPlaceOrder: function () {
            this.unbindButtonClick();
            fullScreenLoader.stopLoader();
            this.onApplePayButtonClicked();
        },
        getCardLogo: function() {
            return window.checkoutConfig.payment.payplug_payments_apple_pay.logo;
        },
        getApplePayLocale: function() {
            return window.checkoutConfig.payment.payplug_payments_apple_pay.locale;
        },
        onApplePayButtonClicked: function() {
            let self = this;
            this.session.onvalidatemerchant = async event => {
                $.ajax({
                    url: url.build(this.getTransactionDataUrl),
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
            window.location.replace(url.build(this.cancelUrl));
        }
    });
});
