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
        session: null,

        initialize: function () {
            this._super();

            this.isVisible(window.ApplePaySession && ApplePaySession.canMakePayments());

            return this;
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
                        "amount": quote.totals()['grand_total']
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
                        window.location.replace(url.build(self.returnUrl));
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
