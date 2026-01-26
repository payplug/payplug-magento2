/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

define([
    'mage/translate',
    'Payplug_Payments/js/view/payment/method-renderer/oney-payment-renderer'
], function ($t, Component) {
    'use strict';

    return Component.extend({
        /**
         * Returns the configuration for the payment method.
         *
         * @returns {Object}
         */
        getConfiguration: function () {
            return window.checkoutConfig.payment.payplug_payments_oney_without_fees;
        },

        /**
         * Returns the label associated with the given payment type.
         *
         * @returns {String}
         */
        getPaymentTypeLabel: function () {
            return $t('Payment in %1 without fees');
        }
    });
});
