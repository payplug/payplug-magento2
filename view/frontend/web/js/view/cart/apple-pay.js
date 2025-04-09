define([
    'jquery',
    'ko',
    'uiComponent',
    'Payplug_Payments/js/apple-pay'
], function ($, ko, Component, payplugApplePay) {
    'use strict';

    return Component.extend({
        applePayIsAvailable: false,
        isVisible: ko.observable(false),

        /**
         * Initializes the component.
         *
         * @returns {void}
         */
        initialize: function () {
            this.applePayIsAvailable = payplugApplePay.getApplePayAvailability();
            this.isVisible(this.applePayIsAvailable);
        },

        /**
         * Handles button click event.
         *
         * @returns {void}
         */
        handleClick: function () {
            if (this.applePayIsAvailable) {
                payplugApplePay.initApplePaySession();
            }
        },
    });
});
