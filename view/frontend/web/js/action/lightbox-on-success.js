define(
    [
        'mage/url',
        'jquery'
    ],
    function (url, jQuery) {
        'use strict';

        return {
            redirectUrl: 'payplug_payments/payment/standard',
            cancelUrl: 'payplug_payments/payment/cancel',

            initialize: function(context) {
                var closeIframe = Payplug._closeIframe;
                Payplug._closeIframe = function(callback) {
                    closeIframe.apply(this, callback);
                    if (typeof window.redirection_url !== 'undefined' && window.redirection_url) {
                        // Payment is completed and redirection is handled by PayPlug form.js
                        return;
                    }
                    context.cancelPayplugPayment();
                };
            },
            /**
             * Provide redirect to page
             */
            execute: function () {
                this.initialize(this);
                var paymentUrl = this.getPayplugPaymentUrl();
                if (paymentUrl !== false) {
                    Payplug.showPayment(paymentUrl);
                } else {
                    this.cancelPayplugPayment();
                }
            },
            getPayplugPaymentUrl: function() {
                var paymentUrl = false;
                jQuery.ajax({
                    url: url.build(this.redirectUrl) + '?should_redirect=0',
                    type: "GET",
                    dataType: 'json',
                    async: false,
                    success: function(response) {
                        if (response.error === true) {
                            alert(response.message);
                            window.location.replace(response.url);
                        } else {
                            paymentUrl = response.url;
                        }
                    }
                });

                return paymentUrl;
            },
            cancelPayplugPayment: function() {
                window.location.replace(url.build(this.cancelUrl));
            }
        };
    }
);
