/* @api */
define([
    'Payplug_Payments/js/view/payment/method-renderer/oney-payment-renderer',
], function (Component) {
    'use strict';

    return Component.extend({
        getConfiguration: function() {
            return window.checkoutConfig.payment.payplug_payments_oney;
        },
        getPaymentTypeLabel: function() {
            return 'Payment in %1';
        }
    });
});
