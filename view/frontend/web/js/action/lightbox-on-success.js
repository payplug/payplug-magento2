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
            returnUrl: 'payplug_payments/payment/paymentReturn',

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
            execute: function (isOneClick = false) {
                this.initialize(this);
                let _this = this;

                jQuery.ajax({
                    url: url.build(this.redirectUrl) + '?should_redirect=0',
                    type: "GET",
                    dataType: 'json',
                    success: function(response) {
                        if (response.error === true) {
                            alert(response.message);
                            window.location.replace(response.url);
                        } else {
                            if (typeof response.is_paid !== 'undefined' && response.is_paid === true) {
                                window.location.replace(url.build(_this.returnUrl));
                            } else {
                                if (typeof response.url !== 'undefined' && response.url !== false) {
                                    Payplug.showPayment(response.url, isOneClick);
                                } else {
                                    _this.cancelPayplugPayment();
                                }
                            }
                        }
                    }
                });
            },
            cancelPayplugPayment: function() {
                window.location.replace(url.build(this.cancelUrl));
            }
        };
    }
);
