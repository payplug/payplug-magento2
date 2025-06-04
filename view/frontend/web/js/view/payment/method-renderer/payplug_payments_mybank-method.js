define([
    'Payplug_Payments/js/view/payment/method-renderer/ppro-payment-renderer'
], function (Component) {
    'use strict';

    return Component.extend({
        /**
         * Get the configuration of Mybank payment method from the global checkout config.
         *
         * @returns {Object}
         */
        getConfiguration: function () {
            return window.checkoutConfig.payment.payplug_payments_mybank;
        }
    });
});
