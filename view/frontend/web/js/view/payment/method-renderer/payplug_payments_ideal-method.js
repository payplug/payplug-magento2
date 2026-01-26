/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

define([
    'Payplug_Payments/js/view/payment/method-renderer/ppro-payment-renderer',
    'jquery',
    'mage/translate'
], function (Component, $) {
    'use strict';

    return Component.extend({
        getConfiguration: function() {
            return window.checkoutConfig.payment.payplug_payments_ideal;
        },
    });
});
