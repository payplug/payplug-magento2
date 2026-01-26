/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

define([
    'Payplug_Payments/js/view/payment/method-renderer/ppro-payment-renderer'
], function (Component) {
    'use strict';

    return Component.extend({
        /**
         * Get the configuration of Satispay payment method from the global checkout config.
         * 
         * @return {Object}
         */
        getConfiguration: function () {
            return window.checkoutConfig.payment.payplug_payments_satispay;
        }
    });
});
