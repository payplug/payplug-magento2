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
                if (typeof Payplug !== 'undefined') {
                    Payplug._closeIframe = function (callback) {
                        var node = document.getElementById("payplug-spinner");
                        if (node) {
                            node.style.display = "none";
                            node.parentNode.removeChild(node);
                        }
                        node = document.getElementById("wrapper-payplug-iframe");
                        if (node) {
                            this._fadeOut(node, function () {
                                if (callback) {
                                    callback();
                                }
                            });
                        }
                        // Hard Remove iframe
                        node.parentNode.removeChild(node);
                        node = document.getElementById("iframe-payplug-close");
                        if (node && node.parentNode) {
                            node.parentNode.removeChild(node);
                        }

                        context.cancelPayplugPayment();
                    }
                }
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
