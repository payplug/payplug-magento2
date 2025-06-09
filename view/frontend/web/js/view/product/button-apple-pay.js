define([
    'jquery',
    'ko',
    'uiComponent',
    'Payplug_Payments/js/view/apple-pay/button-apple-pay'
], function ($, ko, Component, payplugApplePay) {
    'use strict';

    return Component.extend({
        defaults: {
            productFinalPrice: 0,
            productIsVirtual: false
        },
        applePayIsAvailable: false,
        isVisible: ko.observable(false),

        /**
         * Initializes the component.
         *
         * @returns {void}
         */
        initialize: function () {
            this._super();

            this.applePayIsAvailable = payplugApplePay.getApplePayAvailability();
            this.isVisible(this.applePayIsAvailable);
        },

        /**
         * Handles button click event.
         * Create a new quote from the product in the product page
         * And then init Apple Pay Session
         * The async is required as ApplePaySession MUST be created from a user gesture
         *
         * @returns {void}
         */
        handleClick: async function () {
            const isValid = this._checkFormValidation();

            if (!isValid) {
                return;
            }

            payplugApplePay.clearOrderData();
            this._initApplePay();
        },

        /**
         * Validates the product add-to-cart form.
         *
         * @private
         * @returns {boolean} True if the form is valid; otherwise, false.
         */
        _checkFormValidation: function () {
            const form = $('#product_addtocart_form');
            return form.validation('isValid');
        },

        /**
         * Initializes the Apple Pay session with the necessary configuration.
         *
         * @returns {void}
         */
        _initApplePay: function () {
            const amountPrice = this.productFinalPrice;
            const isVirtual = this.productIsVirtual;
            const applePayConfig = this.applePayConfig;
            const { domain, locale, merchand_name: merchandName, currency: currencyCode } = applePayConfig;
            const config = { domain, locale, merchand_name: merchandName, currencyCode };
            const workflowType = this.workflowType;

            if (!amountPrice || !merchandName || !workflowType || !Object.keys(config).length ) {
                return;
            }

            payplugApplePay.setBaseAmount(amountPrice);
            payplugApplePay.setIsVirtual(isVirtual);
            payplugApplePay.setMerchandName(merchandName);
            payplugApplePay.setWorkflowType(workflowType);
            payplugApplePay.initApplePaySession(config);
        }
    });
});
